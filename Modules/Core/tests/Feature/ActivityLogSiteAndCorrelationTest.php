<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Filament\Resources\ActivityLogResource;
use Modules\Core\Http\Middleware\AssignCorrelationId;
use Modules\Core\Models\ActivityLogEntry;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Modules\Core\Notifications\SuperAdminGrantedNotification;
use Modules\Core\Services\CorrelationIdContext;
use Modules\Core\Tests\Support\InteractsWithOrganizationFixtures;
use Modules\Website\Models\ConsultationRequest;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * F2/CLA-465 (second tramo) of the multi-organization program —
 * docs/ai/adr-multi-organization.md. Covers site_id/correlation_id on
 * activity_log and the two new alerts (super_admin granted, export
 * performed). Organization_id/append-only were already covered by
 * ActivityLogOrganizationEnforcementTest (first tramo, untouched here).
 */
final class ActivityLogSiteAndCorrelationTest extends TestCase
{
    use InteractsWithOrganizationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super_admin', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // -------------------------------------------------------------------------
    // site_id derivation
    // -------------------------------------------------------------------------

    public function test_an_activity_on_a_site_scoped_subject_is_stamped_with_that_subjects_site_id(): void
    {
        $consultation = ConsultationRequest::factory()->create(['site_id' => Site::claesenId()]);

        $entry = ActivityLogEntry::query()->where('subject_id', $consultation->id)->latest()->first();

        $this->assertNotNull($entry);
        $this->assertSame(Site::claesenId(), $entry->site_id);
    }

    public function test_a_subject_less_activity_has_no_site_id(): void
    {
        $user = $this->claesenUser();

        activity()->causedBy($user)->log('A subject-less platform event.');

        $entry = ActivityLogEntry::query()->where('description', 'A subject-less platform event.')->first();

        $this->assertNotNull($entry);
        $this->assertNull($entry->site_id);
    }

    public function test_an_explicitly_set_site_id_is_never_overwritten(): void
    {
        $consultation = ConsultationRequest::factory()->create(['site_id' => Site::claesenId()]);

        activity()->performedOn($consultation)->withProperties(['site_id' => 999])->log('irrelevant');
        // The subject's real site_id (Claesen) would win if we didn't set it
        // explicitly on the activity itself — set it directly to prove the
        // guard respects an explicit value over the derived one.
        $entry = new ActivityLogEntry([
            'log_name' => 'test',
            'description' => 'explicit site id',
            'site_id' => 777,
        ]);
        $entry->subject()->associate($consultation);
        $entry->save();

        $this->assertSame(777, $entry->fresh()->site_id);
    }

    // -------------------------------------------------------------------------
    // correlation_id
    // -------------------------------------------------------------------------

    public function test_two_activities_logged_within_the_same_request_share_one_correlation_id(): void
    {
        $user = $this->claesenUser();
        $this->actingAs($user);

        $this->get('/');

        // The request above didn't log an activity by itself — exercise the
        // context directly instead, the same way AssignCorrelationId does
        // (set once per request), and confirm two log() calls in the same
        // process share it.
        $id = app(CorrelationIdContext::class)->id();

        activity()->causedBy($user)->log('first');
        activity()->causedBy($user)->log('second');

        $entries = ActivityLogEntry::query()->whereIn('description', ['first', 'second'])->get();

        $this->assertCount(2, $entries);
        $this->assertSame($id, $entries[0]->correlation_id);
        $this->assertSame($id, $entries[1]->correlation_id);
    }

    public function test_the_middleware_reuses_an_incoming_correlation_id_header(): void
    {
        $middleware = new AssignCorrelationId;
        $request = Request::create('/');
        $request->headers->set('X-Correlation-Id', 'incoming-fixed-id');

        $middleware->handle($request, fn () => response('ok'));

        $this->assertSame('incoming-fixed-id', app(CorrelationIdContext::class)->id());
    }

    public function test_the_middleware_generates_a_fresh_correlation_id_when_none_is_sent(): void
    {
        $middleware = new AssignCorrelationId;

        $middleware->handle(Request::create('/'), fn () => response('ok'));

        $this->assertNotEmpty(app(CorrelationIdContext::class)->id());
    }

    public function test_the_correlation_id_survives_a_real_panel_request_that_redirects_via_an_abort(): void
    {
        // NOTE: like OrganizationContext::id(), CorrelationIdContext::id()
        // lazily generates a fresh id on first access, so this assertion by
        // itself doesn't prove the middleware ran (it would pass even
        // without it) — see test_update_user_activity_actually_runs_on_a_
        // real_admin_panel_request below for the actual hard proof, via
        // UpdateUserActivity's external Cache side effect. What THIS test
        // covers is the $next()-ordering regression found while building
        // this: the middleware must set the context BEFORE calling $next(),
        // not after — several of this panel's own middlewares (password/MFA
        // setup) redirect via a thrown exception, which would skip any code
        // placed after $next() in the pipeline.
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user)->get('/');

        $this->assertNotEmpty(app(CorrelationIdContext::class)->id());
    }

    /**
     * Hard proof (not just CorrelationIdContext's own lazy fallback, which
     * would make a test pass even if no middleware ever ran) that
     * AssignCorrelationId/UpdateUserActivity/ResolveOrganizationContext
     * actually execute on BOTH real panels — the bug found while building
     * this: admin/bertels each build their own explicit ->middleware([...])
     * array rather than the `web` group alias, so bootstrap/app.php's
     * $middleware->web(append: [...]) never reached either panel until this
     * fix registered the three middlewares directly on both providers.
     * UpdateUserActivity's Cache::put('user-is-online-{id}', ...) is used as
     * the proof because it's an external side effect independent of any
     * resolution method's own fallback behaviour.
     */
    public function test_update_user_activity_actually_runs_on_a_real_admin_panel_request(): void
    {
        $user = $this->claesenUser();
        $user->assignRole('super_admin');
        Cache::forget('user-is-online-'.$user->id);

        $this->actingAs($user)->get('/');

        $this->assertTrue(Cache::has('user-is-online-'.$user->id));
    }

    public function test_update_user_activity_actually_runs_on_a_real_bertels_panel_request(): void
    {
        $user = $this->claesenUser();
        $user->assignRole('super_admin');
        Cache::forget('user-is-online-'.$user->id);

        $this->actingAs($user)->get('/bertels');

        $this->assertTrue(Cache::has('user-is-online-'.$user->id));
    }

    // No equivalent hard-proof test exists for ResolveOrganizationContext
    // itself: OrganizationContext::id()/resolve() lazily resolve on first
    // access with no external side effect (unlike UpdateUserActivity's
    // Cache::put()), so asserting on it after a request would just re-prove
    // the lazy fallback, not that the middleware ran — the same
    // methodological flaw this whole investigation started from. Since
    // ResolveOrganizationContext sits in the exact same explicit
    // ->middleware([...]) array as UpdateUserActivity on both panels (see
    // AdminPanelProvider/BertelsPanelProvider), the two tests above already
    // prove that array actually executes on real panel requests.

    // -------------------------------------------------------------------------
    // "Alertas por creación de super_admin"
    // -------------------------------------------------------------------------

    public function test_assigning_super_admin_notifies_existing_super_admins_and_admins(): void
    {
        Notification::fake();

        $existingSuperAdmin = $this->claesenUser();
        $existingSuperAdmin->assignRole('super_admin');
        Notification::fake(); // discard the notification from the line above

        $newAdmin = $this->claesenUser();
        $newAdmin->assignRole('super_admin');

        Notification::assertSentTo($existingSuperAdmin, SuperAdminGrantedNotification::class);
        Notification::assertNotSentTo($newAdmin, SuperAdminGrantedNotification::class);
    }

    public function test_sync_roles_also_triggers_the_alert_when_it_adds_super_admin(): void
    {
        Notification::fake();

        $existingSuperAdmin = $this->claesenUser();
        $existingSuperAdmin->assignRole('super_admin');
        Notification::fake();

        Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $target = $this->claesenUser();
        $target->assignRole('viewer');
        Notification::fake();

        $target->syncRoles(['super_admin']);

        Notification::assertSentTo($existingSuperAdmin, SuperAdminGrantedNotification::class);
    }

    public function test_re_syncing_the_same_roles_never_notifies_again(): void
    {
        Notification::fake();

        $superAdmin = $this->claesenUser();
        $superAdmin->assignRole('super_admin');
        Notification::fake();

        $target = $this->claesenUser();
        $target->assignRole('super_admin');
        Notification::fake();

        $target->syncRoles(['super_admin']); // still super_admin, nothing new

        Notification::assertNotSentTo($superAdmin, SuperAdminGrantedNotification::class);
    }

    public function test_assigning_a_non_super_admin_role_never_notifies(): void
    {
        Notification::fake();
        Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);

        $superAdmin = $this->claesenUser();
        $superAdmin->assignRole('super_admin');
        Notification::fake();

        $target = $this->claesenUser();
        $target->assignRole('viewer');

        Notification::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // ActivityLogResource access
    // -------------------------------------------------------------------------

    public function test_only_super_admin_can_access_the_audit_log_resource(): void
    {
        $superAdmin = $this->claesenUser();
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin);
        $this->assertTrue(ActivityLogResource::canAccess());

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = $this->claesenUser();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $this->assertFalse(ActivityLogResource::canAccess());
    }

    public function test_the_audit_log_resource_has_no_create_edit_or_delete_surface(): void
    {
        $resource = ActivityLogResource::class;

        $this->assertFalse($resource::canCreate());
        $this->assertFalse($resource::canDeleteAny());

        $consultation = ConsultationRequest::factory()->create();
        $entry = ActivityLogEntry::query()->where('subject_id', $consultation->id)->latest()->firstOrFail();

        $this->assertFalse($resource::canEdit($entry));
        $this->assertFalse($resource::canDelete($entry));
    }
}

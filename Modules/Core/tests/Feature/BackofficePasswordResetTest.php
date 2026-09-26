<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Database\Factories\UserFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Core\Filament\Pages\Auth\RequestPasswordReset;
use Modules\Core\Filament\Pages\Auth\ResetPassword;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Modules\Core\Notifications\PasswordResetNotification;
use Tests\TestCase;

/**
 * CLA-603: the backoffice login's forgot/reset screens (the approved mockup).
 *
 * What matters here is not the HTML — it is that the panels reuse the project's
 * own reset mechanism (hashed activation code, local accounts only) instead of
 * Filament's password_reset_tokens broker, and that the emailed link points at
 * the panel the user asked from.
 */
final class BackofficePasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function activeUser(array $overrides = []): User
    {
        return UserFactory::new()->create(array_merge([
            'password' => bcrypt('OldSecret123!'),
            'password_set_at' => now()->subDay(),
            'is_active' => true,
        ], $overrides));
    }

    private function bertelsSite(): Site
    {
        $organization = Organization::factory()->create([
            'slug' => 'electro-bertels',
            'name' => 'Electro Bertels',
        ]);

        return Site::factory()->create([
            'organization_id' => $organization->id,
            'key' => 'electro-bertels',
        ]);
    }

    private function issueResetCode(User $user): string
    {
        $code = Str::random(64);

        $user->forceFill([
            'activation_code_hash' => hash('sha256', $code),
            'activation_code_expires_at' => now()->addMinutes(30),
        ])->saveQuietly();

        return $code;
    }

    public function test_both_panels_expose_the_password_screens(): void
    {
        $this->assertTrue(route('filament.admin.auth.password-reset.request') !== null);
        $this->assertTrue(route('filament.bertels.auth.password-reset.request') !== null);

        $this->get('/password-reset/request')->assertOk();
        $this->get('/bertels/password-reset/request')->assertOk();
    }

    public function test_the_screens_render_the_panels_own_brand_column(): void
    {
        $this->bertelsSite();

        $this->get('/bertels/password-reset/request')
            ->assertOk()
            ->assertSee('bertels-brand-logo-dark.png')
            ->assertDontSee('claesen-logo-login.png');

        // CLA-603: the css no longer depends on the admin panel's Vite theme, so
        // the same page must carry the theme partial on the bertels panel too.
        $this->get('/bertels/password-reset/request')->assertSee('cafca-login-theme', false);
    }

    public function test_requesting_a_reset_emails_a_link_to_that_panels_own_screen(): void
    {
        Notification::fake();
        $this->bertelsSite();
        $user = $this->activeUser();

        Filament::setCurrentPanel('bertels');

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $user->email])
            ->call('request')
            ->assertHasNoFormErrors()
            ->assertSet('sent', true)
            ->assertSet('sentTo', $user->email);

        Notification::assertSentTo($user, PasswordResetNotification::class, function (PasswordResetNotification $notification) use ($user): bool {
            return str_contains(
                (string) $notification->toMail($user)->actionUrl,
                '/bertels/password-reset/reset',
            );
        });

        $this->assertNotNull($user->fresh()->activation_code_hash);
    }

    public function test_an_unknown_address_sees_the_same_screen_and_nothing_is_emailed(): void
    {
        Notification::fake();

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'nobody@example.com'])
            ->call('request')
            ->assertHasNoFormErrors()
            ->assertSet('sent', true);

        Notification::assertNothingSent();
    }

    public function test_the_sent_screen_replaces_the_form_instead_of_stacking_on_it(): void
    {
        // CLA-603: Filament memoizes the page's content schema, so the swap has to
        // be driven by a Closure (->visible(fn () => ...)). With a plain boolean the
        // form stayed visible and the sent block never rendered — this is the
        // regression that pins it.
        Notification::fake();

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'nobody@example.com'])
            ->call('request')
            ->assertSet('sent', true)
            ->assertSee('cafca-login-panel')
            ->assertDontSee('fi-sc-form');
    }

    public function test_a_microsoft_only_account_never_receives_a_link(): void
    {
        Notification::fake();
        $user = $this->activeUser(['microsoft_id' => 'azure-oid-123']);

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $user->email])
            ->call('request')
            ->assertSet('sent', true);

        Notification::assertNothingSent();
    }

    public function test_the_reset_screen_sets_the_new_password_and_ends_on_the_success_screen(): void
    {
        $user = $this->activeUser();
        $code = $this->issueResetCode($user);

        Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => $code])
            ->fillForm([
                'password' => 'BrandNew123!',
                'passwordConfirmation' => 'BrandNew123!',
            ])
            ->call('resetPassword')
            ->assertSet('done', true);

        $fresh = $user->fresh();

        $this->assertTrue(Hash::check('BrandNew123!', $fresh->password));
        $this->assertNull($fresh->activation_code_hash);
    }

    public function test_the_success_screen_replaces_the_form_instead_of_stacking_on_it(): void
    {
        // @see test_the_sent_screen_replaces_the_form_instead_of_stacking_on_it
        $user = $this->activeUser();
        $code = $this->issueResetCode($user);

        Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => $code])
            ->fillForm([
                'password' => 'BrandNew123!',
                'passwordConfirmation' => 'BrandNew123!',
            ])
            ->call('resetPassword')
            ->assertSet('done', true)
            ->assertSee('cafca-login-panel')
            ->assertDontSee('fi-sc-form');
    }

    public function test_an_expired_code_is_rejected_and_the_password_is_untouched(): void
    {
        $user = $this->activeUser();
        $code = Str::random(64);

        $user->forceFill([
            'activation_code_hash' => hash('sha256', $code),
            'activation_code_expires_at' => now()->subMinute(),
        ])->saveQuietly();

        Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => $code])
            ->fillForm([
                'password' => 'BrandNew123!',
                'passwordConfirmation' => 'BrandNew123!',
            ])
            ->call('resetPassword')
            ->assertSet('done', false);

        $this->assertTrue(Hash::check('OldSecret123!', $user->fresh()->password));
    }

    public function test_the_reset_screen_cannot_be_reached_without_a_signed_url(): void
    {
        $this->bertelsSite();
        $user = $this->activeUser();
        $code = $this->issueResetCode($user);

        $this->get('/bertels/password-reset/reset?email='.urlencode($user->email).'&token='.$code)
            ->assertForbidden();

        $this->get(Filament::getPanel('bertels')->getResetPasswordUrl($code, $user))->assertOk();
    }
}

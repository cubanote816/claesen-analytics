<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Clusters\Website\Pages\PublicationHistoryPage;
use App\Filament\Clusters\Website\Widgets\UnpublishedChangesWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Modules\Core\Models\ActivityLogEntry;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Modules\Website\Models\PublicationState;
use Modules\Website\Models\SiteSetting;
use Modules\Website\Services\StaticSitePublicationService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * WU4 de odd/tasks/publish-to-live-website.md, lado pantalla: que desde el panel se pueda
 * responder «¿quién publicó y cuándo?» y «¿qué cambió desde la última publicación?».
 *
 * La ventana es la parte que hay que probar de verdad: una lista de cambios que **no** filtra
 * por la última publicación aceptada responde otra pregunta («qué cambió alguna vez») y desde
 * la pantalla se ve idéntica. Por eso hay un cambio a cada lado de la marca y se afirma que
 * sólo aparece uno.
 */
final class PublicationHistoryPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        config([
            'static_site.enabled'          => true,
            'static_site.webhook_url'      => 'http://fake-frontend:9000/rebuild',
            'static_site.webhook_secret'   => 'test-secret',
            'static_site.debounce_seconds' => 20,
        ]);
    }

    private function actingAsSuperAdmin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super_admin');

        $this->actingAs($user);

        return $user;
    }

    private function setting(string $key, string $value): SiteSetting
    {
        return SiteSetting::factory()->create([
            'key'   => $key,
            'type'  => SiteSetting::TYPE_TEXT,
            'value' => $value,
        ]);
    }

    public function test_the_history_screen_shows_who_asked_for_a_publication(): void
    {
        Queue::fake(); // El job no debe correr inline: acá se prueba el rastro de la solicitud.
        $user = $this->actingAsSuperAdmin();

        app(StaticSitePublicationService::class)->requestRebuild(Site::claesenId(), 'manual', force: true);

        $request = ActivityLogEntry::query()
            ->where('log_name', StaticSitePublicationService::PUBLICATION_LOG)
            ->where('site_id', Site::claesenId())
            ->sole();

        $this->assertSame($user->id, $request->causer_id);

        Livewire::test(PublicationHistoryPage::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$request]);
    }

    /**
     * Los cambios posteriores a la última publicación **aceptada** se ven; los anteriores no.
     * Ese corte es la respuesta a «¿qué cambió desde la última publicación?», y sin él la lista
     * sería un historial completo disfrazado de pendientes.
     *
     * El corte es un **id** de actividad y no una fecha, y esto no es un detalle: con fechas el
     * test fallaba de forma determinista, porque los `timestamp` de MySQL llegan al segundo y un
     * cambio guardado en el mismo segundo que la publicación quedaba indistinguible de ella.
     * Acá la marca se toma como la toma el job: justo antes de publicar.
     */
    public function test_the_changes_list_is_windowed_to_the_last_accepted_publication(): void
    {
        $this->actingAsSuperAdmin();

        $before = $this->setting('phone', '+32 000');   // antes de publicar

        $watermark = ActivityLogEntry::query()->max('id');

        $after = $this->setting('email', 'info@electrobertels.be');  // después de publicar

        PublicationState::current(Site::claesenId())->markAccepted($watermark);

        $beforeChange = ActivityLogEntry::query()->where('subject_id', $before->id)
            ->where('subject_type', SiteSetting::class)->sole();
        $afterChange = ActivityLogEntry::query()->where('subject_id', $after->id)
            ->where('subject_type', SiteSetting::class)->sole();

        Livewire::test(UnpublishedChangesWidget::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$afterChange])
            ->assertCanNotSeeTableRecords([$beforeChange]);
    }

    /**
     * Sin ninguna publicación aceptada no hay ventana que aplicar: se ve todo lo registrado, en
     * vez de una pantalla vacía que haría pensar que no hubo cambios.
     */
    public function test_without_an_accepted_publication_every_recorded_change_is_listed(): void
    {
        $this->actingAsSuperAdmin();

        $setting = $this->setting('phone', '+32 111');
        $change = ActivityLogEntry::query()->where('subject_id', $setting->id)
            ->where('subject_type', SiteSetting::class)->sole();

        Livewire::test(UnpublishedChangesWidget::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$change]);
    }
}

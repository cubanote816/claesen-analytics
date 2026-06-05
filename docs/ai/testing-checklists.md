# Checklists de testing — CAFCA Intelligence Hub

> Qué testear según el tipo de cambio y módulo.
> Última actualización: 2026-06-03 (TEST-GATE-001)

> **Este archivo es el checklist técnico de referencia.**
> La obligatoriedad, la matriz de tests por tipo de cambio, los criterios de waiver y la plantilla de cierre están en **`docs/ai/test-gate-harness.md`**. Leer ese archivo antes de planificar o revisar cualquier ticket que toque código.

---

## Comandos de test

```bash
# Suite completa
php artisan test

# Solo módulos (todos los *Test.php bajo Modules/)
php artisan test --testsuite=Modules

# Un módulo concreto
php artisan test --testsuite=Modules --filter=Mailing
php artisan test --testsuite=Modules --filter=Safety
php artisan test --testsuite=Modules --filter=Intelligence
php artisan test --testsuite=Modules --filter=Performance
php artisan test --testsuite=Modules --filter=Prospects
php artisan test --testsuite=Modules --filter=Website
php artisan test --testsuite=Modules --filter=Cafca

# Un archivo concreto
php artisan test Modules/Mailing/tests/Feature/CampaignWorkflowTest.php

# Unit tests raíz
php artisan test --testsuite=Unit

# Feature tests raíz
php artisan test --testsuite=Feature
```

**Configuración phpunit.xml:**
- Suite `Unit` → `tests/Unit/`
- Suite `Feature` → `tests/Feature/`
- Suite `Modules` → todos los `*Test.php` bajo `Modules/`
- `DB_DATABASE=testing` (MySQL de test)
- `QUEUE_CONNECTION=sync` (jobs síncronos en tests)
- `MAIL_MAILER=array` (correos en memoria)

**Nota:** los tests de módulos requieren MySQL (no SQLite). Necesita `sail up -d mysql` o un servidor MySQL disponible.

---

## Tests existentes por módulo

### Mailing (11 archivos)

```
Modules/Mailing/tests/Feature/
├── AbTestingTest.php              ← A/B test split, winner por CTR
├── BounceParserTest.php           ← parsing NDR bounces
├── CampaignWorkflowTest.php       ← flujo completo draft→completed
├── DeliverabilityAlertTest.php    ← umbrales hard bounce y spam
├── DispatchScheduledTest.php      ← campaña programada con scheduled_at
├── FollowUpTest.php               ← follow-up por comportamiento
├── ListUnsubscribeTest.php        ← headers List-Unsubscribe
├── NdrCorrelationTest.php         ← correlación X-Mailing-Token en NDR
├── PreferencesControllerTest.php  ← página de preferencias de categoría
├── SchemaFoundationTest.php       ← estructura de tablas Fase 2
├── SegmentResolverTest.php        ← resolución de segmentos dinámicos
├── SuppressionServiceTest.php     ← supresión permanente y temporal
└── TrackingControllerTest.php     ← open pixel y click redirect
```

### Safety (8 archivos)

```
Modules/Safety/tests/Feature/
├── ChecklistIndexTest.php
├── ComplianceControllerTest.php
├── InspectionAuthStoreIndexTest.php
├── InspectionDownloadPdfTest.php
├── InspectionPhotoStorageFailureTest.php
├── InspectionServePhotoTest.php
├── InspectionShowTest.php
└── SafetyFileControllerTest.php
```

---

## Checklist por tipo de cambio

### Migration nueva

- [ ] La migración es reversible (`down()` implementado)
- [ ] Los nombres de tabla siguen la convención del módulo (ej: `mailing_*`, `safety_*`)
- [ ] Las columnas nullable tienen `->nullable()` explícito
- [ ] Las foreign keys tienen `->constrained()` y `->cascadeOnDelete()` si aplica
- [ ] Ejecutar: `php artisan migrate --database=testing` sin error
- [ ] Rollback: `php artisan migrate:rollback --database=testing` sin error
- [ ] Si es Fase 2 de Mailing: añadir a la sección "Migraciones a ejecutar" del doc maestro

### Modelo nuevo

- [ ] Si es Cafca/ERP: extends `CafcaModel`, usa `ReadOnlyTrait`, IDs con `trim()`
- [ ] Si es MySQL local: extends `Illuminate\Database\Eloquent\Model`
- [ ] Fillable declarado correctamente
- [ ] Casts definidos (especialmente `Enum`, `datetime`, `decimal`)
- [ ] Factory creada (mínimo campos obligatorios)
- [ ] Test que verifica creación y relaciones básicas

### Service nuevo

- [ ] Lógica en el service, no en el controller
- [ ] No llamar a `MicrosoftGraphMailer` directamente (usar `MarketingCampaignInterface`)
- [ ] Inyección de dependencias via constructor (no `app()->make()` salvo jobs)
- [ ] Test unitario o feature del service con mocks del transport si aplica
- [ ] Comportamiento idempotente si el service puede ejecutarse varias veces

### Job nuevo

- [ ] Implementa `ShouldQueue`
- [ ] Define `$tries` y `$backoff` apropiados
- [ ] Manejo de excepciones con `$this->fail()` para errores no recuperables
- [ ] Test con `Queue::fake()` o con `QUEUE_CONNECTION=sync`
- [ ] Si es Mailing: verifica que no envía a contactos suprimidos

### Command nuevo

- [ ] Añadido a `commands-runbook.md`
- [ ] Si aplica: soporta `--dry-run` para previsualizar sin cambios reales
- [ ] Si aplica: soporta `--limit` o `--batch` para control de volumen
- [ ] Añadir al scheduler en `app/Console/Kernel.php` si es periódico
- [ ] Test que verifica output y efecto del command

### Policy nueva / modificada

- [ ] `super_admin` tiene acceso total
- [ ] `project_manager` tiene acceso solo a sus propios recursos
- [ ] Test explícito de `allow` y `deny` para cada rol
- [ ] No usar `authorize()` del Form Request para recursos (usar `Gate::authorize()`)

### Filament Resource nuevo

- [ ] Usa `Filament\Schemas\Schema` (no clases V3/V4)
- [ ] Labels en NL y EN si aplica
- [ ] Permisos RBAC configurados en el Resource
- [ ] Verificar manualmente en el panel (los tests no cubren UI de Filament)

### API endpoint nuevo

- [ ] Ruta registrada en `routes/api.php` del módulo correcto
- [ ] Middleware de autenticación correcto (`auth:sanctum` o público)

**Endpoint protegido (`auth:sanctum` requerido):**
- [ ] 200 OK happy path con token válido
- [ ] 401 sin token
- [ ] 403 sin permiso (rol insuficiente)
- [ ] 422 input inválido

**Endpoint público (sin auth, p. ej. `/v1/website/*`):**
- [ ] 200 OK happy path
- [ ] 422 si recibe input inválido (solo si el endpoint acepta input)
- [ ] Estructura JSON consistente con el resto de la API
- [ ] Si devuelve campos traducibles: verificar locale correcto según `Accept-Language` y fallback

### Cambio en módulo Mailing

- [ ] `php artisan test --filter=BounceParserTest`
- [ ] `php artisan test --filter=CampaignWorkflowTest`
- [ ] `php artisan test --filter=SuppressionServiceTest`
- [ ] `php artisan test --filter=ListUnsubscribeTest`
- [ ] `php artisan test --filter=NdrCorrelationTest`
- [ ] Si afecta scheduling: `php artisan test --filter=DispatchScheduledTest`
- [ ] Si afecta A/B: `php artisan test --filter=AbTestingTest`
- [ ] Si afecta follow-up: `php artisan test --filter=FollowUpTest`
- [ ] Si afecta alertas: `php artisan test --filter=DeliverabilityAlertTest`
- [ ] Si afecta tracking: `php artisan test --filter=TrackingControllerTest`
- [ ] `php artisan mailing:parse-bounces --dry-run`
- [ ] `php artisan mailing:dispatch-scheduled --dry-run`
- [ ] `php artisan mailing:ab-select-winner --dry-run`
- [ ] `php artisan mailing:dispatch-followups --dry-run`
- [ ] `php artisan mailing:check-deliverability-alerts --dry-run`

### Cambio en módulo Safety

- [ ] `php artisan test --testsuite=Modules --filter=Safety`
- [ ] Verificar que `config('safety.disk')` → `local`
- [ ] Verificar autorización `project_manager` vs `super_admin`

### Cambio en módulo Website

- [ ] `php artisan test --testsuite=Modules --filter=Website` (si existen tests)
- [ ] Verificar que solo proyectos `published = true` aparecen en la API
- [ ] Si se modificaron conversiones: `php artisan website:regenerate-media --dry-run` (si soporta)

---

## Smoke tests de comandos Mailing (antes de producción)

```bash
# Ver sin ejecutar
php artisan mailing:dispatch-scheduled --dry-run
php artisan mailing:parse-bounces --dry-run
php artisan mailing:ab-select-winner --dry-run
php artisan mailing:dispatch-followups --dry-run
php artisan mailing:check-deliverability-alerts --dry-run
```

Verificar que cada comando:
- Sale con código 0
- Muestra output coherente (número de campañas procesadas, etc.)
- No lanza excepciones no capturadas

---

## Notas sobre el entorno de tests

- **MySQL requerido:** los tests de módulos no funcionan con SQLite (hay JSON columns, CTEs y joins cross-module).
- **Sail:** `sail up -d` o tener MySQL 8.4 disponible en `localhost:3306`.
- **DB de test:** `DB_DATABASE=testing` (phpunit.xml). No usa la DB de desarrollo.
- **Queues síncronas:** `QUEUE_CONNECTION=sync` en tests (los jobs se ejecutan inline).
- **Mail en array:** `MAIL_MAILER=array` (los emails se capturan en memoria, no se envían).
- **Sin Graph API real:** los tests de Mailing mockean `MicrosoftGraphMailer` o usan `Mail::fake()`.
- **Sin Gemini real:** `GeminiService` siempre mockeado en tests — nunca llamadas reales a la API.

---

## Lecciones del módulo Website — sprint WEB-008 → WEB-011

Estos patrones emergieron del primer sprint multidioma del módulo Website. Se implementaron sin tests automatizados — deuda histórica documentada; no constituye waiver para tickets futuros. Los tests recomendados a continuación deben añadirse en WEB-012 o en la primera iteración que toque estos componentes.

### i18n — middleware de locale y traducciones

**Archivos afectados:** `SetPanelLocale.php`, `BrowserLocaleMiddleware.php`, `lang/en,nl,fr,de/website.php`

Tests recomendados:
```php
// Feature: middleware resuelve locale correcto por Accept-Language
it('sets locale from Accept-Language header', function () {
    $this->withHeader('Accept-Language', 'fr-BE,fr;q=0.9')
         ->getJson('/v1/website/projects')
         ->assertOk();
    expect(app()->getLocale())->toBe('fr');
});

// Unit: locale desconocido cae a 'en'
it('falls back to en for unsupported locale', function () {
    // SetPanelLocale::resolveLocale('es') → 'en'
});
```

### AI de galería — HasAiTranslations + GenerateGalleryMediaMetadataJob

**Archivos afectados:** `HasAiTranslations.php`, `GenerateGalleryMediaMetadataJob.php`, `GeminiService.php`

Tests recomendados:
```php
// Feature: subir imagen de galería dispara job
it('dispatches GenerateGalleryMediaMetadataJob on gallery media saved', function () {
    Bus::fake();
    // crear proyecto + adjuntar media a gallery
    Bus::assertDispatched(GenerateGalleryMediaMetadataJob::class);
});

// Unit: job con GeminiService mockeado rellena caption/alt en 4 locales
it('stores caption and alt in all locales on success', function () {
    $gemini = Mockery::mock(GeminiService::class);
    $gemini->shouldReceive('generateMediaMetadata')->andReturn([
        'caption' => ['nl' => 'Cap NL', 'en' => 'Cap EN', 'fr' => 'Cap FR', 'de' => 'Cap DE'],
        'alt'     => ['nl' => 'Alt NL', 'en' => 'Alt EN', 'fr' => 'Alt FR', 'de' => 'Alt DE'],
    ]);
    // ejecutar job → assertar custom_properties en media
});

// Unit: Gemini falla → media guardada, caption vacío, no excepción propagada
it('stores empty metadata and logs on Gemini failure', function () { ... });
```

### Email de Consultation Requests

**Archivos afectados:** `ConsultationService.php`, `NewConsultationRequestMail.php`

Tests recomendados:
```php
// Feature: POST /consultations envía correo después del commit
it('sends NewConsultationRequestMail on consultation created', function () {
    Mail::fake();
    $this->postJson('/v1/website/consultations', [...datos válidos...])
         ->assertCreated();
    Mail::assertSent(NewConsultationRequestMail::class, fn($mail) =>
        $mail->hasTo('orelvys.cuellar@claesen-verlichting.be')
    );
});

// Feature: fallo de correo no impide guardar la consulta
it('saves consultation even if mail fails', function () {
    Mail::shouldReceive('mailer')->andThrow(new \Exception('Graph error'));
    $this->postJson('/v1/website/consultations', [...])
         ->assertCreated();
    $this->assertDatabaseHas('website_consultation_requests', ['email' => '...']);
});
```

### ProcessConsultationRemindersCommand — claim atómico

**Archivos afectados:** `ProcessConsultationRemindersCommand.php`, `ConsultationService.php`

Tests recomendados:
```php
// Feature: reminder vencido se procesa y queda 'completed'
it('marks due reminder as completed and logs activity', function () {
    Notification::fake();
    $reminder = ConsultationReminder::factory()->create([
        'remind_at' => now()->subMinute(),
        'status' => 'pending',
    ]);
    $this->artisan('website:process-reminders')->assertExitCode(0);
    expect($reminder->fresh()->status)->toBe('completed');
    $this->assertDatabaseHas('website_consultation_activities', [
        'consultation_request_id' => $reminder->consultation_request_id,
        'type' => 'reminder_triggered',
    ]);
});

// Feature: reminder en 'processing' no se duplica
it('skips reminder already in processing state', function () { ... });
```

### Double logging — updateQuietly en ConsultationService

**Archivos afectados:** `ConsultationService.php`, `ConsultationRequestObserver.php`

Tests recomendados:
```php
// Feature: updateStatus() crea exactamente 1 actividad de tipo status_change
it('logs exactly one status_change activity via updateStatus', function () {
    $request = ConsultationRequest::factory()->create(['status' => 'pending']);
    app(ConsultationService::class)->updateStatus($request, 'contacted');
    $this->assertDatabaseCount('website_consultation_activities', 1);
    $this->assertDatabaseHas('website_consultation_activities', ['type' => 'status_change']);
});
```

### Migración varchar → JSON con JSON_VALID

**Archivos afectados:** `2026_06_02_000001_convert_location_client_to_json_in_website_projects.php`

Tests recomendados:
```php
// Feature: migración convierte string plano a {"nl": "valor"}
it('wraps plain strings as nl JSON on migration', function () {
    DB::table('website_projects')->insert(['location' => 'Gent', ...]);
    Artisan::call('migrate', ['--path' => '...migration...']);
    $row = DB::table('website_projects')->first();
    expect(json_decode($row->location, true))->toBe(['nl' => 'Gent']);
});

// Feature: migración preserva JSON existente intacto
it('preserves existing JSON object on migration', function () {
    DB::table('website_projects')->insert([
        'location' => json_encode(['nl' => 'Gent', 'en' => 'Ghent']), ...
    ]);
    // después de migrate: location sigue siendo {"nl":"Gent","en":"Ghent"}
});
```

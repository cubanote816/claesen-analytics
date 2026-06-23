# Contratos por módulo — Reglas no negociables

> Estas reglas no tienen excepciones. Violarlas introduce bugs de seguridad, corrupción de datos
> o incumplimiento de compliance. Verificar antes de cualquier implementación.

---

## Reglas globales (todos los módulos)

1. **SQL Server es ReadOnly.** Jamás usar `save()`, `update()`, `create()`, `delete()` sobre la conexión `sqlsrv`. Los modelos Cafca tienen `ReadOnlyTrait` que lanza `LogicException` si se intenta mutar.

2. **Filament V5 únicamente.** Usar `Filament\Schemas\Schema` para Forms e Infolists. Las clases de V3/V4 no existen en esta versión y causarán errores fatales.

3. **IDs del ERP son strings.** Los IDs de SQL Server nunca son enteros. Siempre `trim()` al asignarlos o compararlos en modelos Cafca.

4. **Idioma:** código, variables y comentarios en inglés. UI, labels y notificaciones en holandés (NL) para navegadores NL, inglés para el resto.

5. **Sin ticket Linear activo no se edita código.** Ver `project-protocol.md`.

---

## Módulo Cafca

### Reglas

- **Nunca mutar modelos Cafca.** `ReadOnlyTrait` bloqueará cualquier intento, pero no confiar en ello como primera línea de defensa.
- Los modelos de Cafca usan la conexión `sqlsrv`. Verificar con `protected $connection = 'sqlsrv'`.
- Siempre `trim()` en los IDs: `$this->attributes['id'] = trim($value)`.
- Los modelos Mirror en Performance (`MirrorProject`, `MirrorCost`, etc.) son la copia MySQL local; sobre estos sí se pueden hacer queries analíticas.
- No añadir relaciones `hasMany` / `belongsTo` que apunten de SQL Server a MySQL o viceversa sin una capa de servicio intermedia.

### Archivos clave
```
Modules/Cafca/Models/CafcaModel.php         ← base con ReadOnlyTrait
Modules/Cafca/Models/Project.php
Modules/Cafca/Services/CafcaSyncService.php
```

---

## Módulo Mailing

### Reglas de transporte

- **Siempre usar `MarketingCampaignInterface`** para enviar correos. Nunca llamar `MicrosoftGraphMailer` directamente desde controllers o jobs.
- `MicrosoftGraphMailer` es la implementación actual. `SaaSMailer` es un stub para ESP futuro (MAI-026, bloqueado).
- Si se añade un nuevo mailer, debe implementar `App\Contracts\MarketingCampaignInterface`.

### Reglas de aprobación

- **Sin aprobación no hay envío.** `ExecuteCampaignJob` lanza `DomainException` si `campaign->status !== approved`.
- El workflow de estados es: `draft → review → approved → sending → completed|failed|cancelled`.
- Una campaña en `sending` o terminal no puede volver atrás (ver `CampaignStatus::allowedTransitions()`).

### Reglas de supresión

- **No enviar a contactos suprimidos.** `SuppressionService` debe consultarse antes de cada envío.
- `hard_bounce` y `spam_complaint` son **permanentes**. Solo `super_admin` puede levantar la supresión.
- `unsubscribed` y `manual` son permanentes pero levantables con permiso adecuado.
- `soft_bounce_limit` se activa al llegar al límite configurado en `mailing.bounce_soft_limit` (por defecto: 3).

### Reglas de tracking y eventos

- **`mailing_message_events` es append-only.** Nunca editar ni eliminar registros de eventos existentes.
- Aperturas son señal débil (Apple MPP, proxies corporativos). **No usar open rate como KPI principal.**
- KPIs válidos: **CTR** (click-through rate), **CTOR** (click-to-open rate), clics únicos, bajas, quejas.

### Reglas de compliance

- **`List-Unsubscribe` y `List-Unsubscribe-Post` son obligatorios** en todo correo comercial (`TemplateCategory::COMMERCIAL`).
- Exención: correos transaccionales (`TemplateCategory::TRANSACTIONAL`).
- El header `X-Mailing-Token` debe incluirse en todos los correos para correlación exacta de NDR.

### Reglas de A/B testing

- Un A/B test se inicia solo si la campaña tiene `ab_subject_b` configurado.
- El ganador se selecciona por CTR (no por aperturas).
- Mínimo de muestra: `mailing.ab_min_sample` (por defecto: 5 por variante).

### Reglas de follow-up

- Un follow-up solo se despacha si la campaña padre está en estado `completed`.
- Si la audiencia filtrada del follow-up está vacía, el job lo marca como despachado sin error (empty-audience safe).
- No crear ciclos indirectos (A → follow-up B → follow-up A). No están bloqueados técnicamente — responsabilidad del operador.

### Reglas de alertas de entregabilidad

- Umbral hard bounce: `mailing.hard_bounce_alert` (por defecto: 5%).
- Umbral spam complaint: `mailing.spam_rate_alert` (por defecto: 0.08%).
- Las alertas se almacenan en `mailing_deliverability_alerts` y se notifican via DB notifications.

### Fase 3

- **Bloqueada hasta tener 4–6 semanas de datos reales** de campañas en producción.
- No iniciar MAI-031 a MAI-036 hasta esa condición.

### Variables de entorno requeridas (sin valores)

```env
MAILING_NDR_INBOX=<inbox para NDR bounces>
MAILING_SEND_DELAY_MS=<delay en ms entre envíos>
MAILING_UNSUBSCRIBE_DOMAIN=<dominio para links de baja>
MAILING_TRACKING_DOMAIN=<dominio para tracking>
```

### Archivos clave
```
App/Contracts/MarketingCampaignInterface.php
Modules/Mailing/Models/Campaign.php
Modules/Mailing/Models/CampaignMessage.php
Modules/Mailing/Models/MessageEvent.php
Modules/Mailing/Models/SuppressionEntry.php
Modules/Mailing/Services/MicrosoftGraphMailer.php
Modules/Mailing/Services/SuppressionService.php
Modules/Mailing/Services/SegmentResolverService.php
Modules/Mailing/Enums/CampaignStatus.php
Modules/Mailing/Jobs/ExecuteCampaignJob.php
```

---

## Módulo Safety

### Reglas de disco

- Fotos y PDFs se almacenan en disco `local` (privado).
- Configuración: `config('safety.disk')` → valor `local`.
- Nunca usar disco `public` para archivos de Safety.
- El acceso a archivos privados se sirve via `SafetyFileController` con autorización.

### Reglas de autorización

- `Gate::authorize()` por recurso en todos los controllers de Safety.
- No cambiar el padre del controller (no usar `authorize()` del Form Request para recursos).
- `project_manager` → solo ve sus propios recursos (`inspection.user_id === auth()->id()`).
- `super_admin` → ve todos los recursos.
- La política es `InspectionPolicy`. Tests y factories deben estar dentro de `Modules/Safety`.

### Reglas de modelos y adopción

- `Inspection` tiene `idempotency_key` para prevenir duplicados.
- `Answer` puede tener foto adjunta almacenada en disco local privado.
- Las migraciones usan nombres de tabla con prefijo `safety_`.
- **Métricas de Adopción:** El cálculo del denominador ("usuarios habilitados") debe derivarse estrictamente de los roles autorizados en `EnsureSafetyAccess.php`.
- Los rollups diarios de adopción usan `project_id = 'GLOBAL'` como valor predeterminado para evitar fallos de unicidad (unique constraints) con NULL en MySQL.

### Archivos clave
```
Modules/Safety/Policies/InspectionPolicy.php
Modules/Safety/Services/ComplianceService.php
Modules/Safety/Http/Controllers/InspectionController.php
Modules/Safety/Http/Controllers/SafetyFileController.php
Modules/Safety/Services/SafetyAdoptionMetricsService.php
Modules/Safety/Console/Commands/AggregateSafetyAdoptionMetricsCommand.php
Modules/Safety/config/config.php
```

---

## Módulo Website

### Reglas de API pública

- Todas las rutas bajo `/v1/website/` son **públicas** (sin auth).
- La API solo expone proyectos con `published = true`.
- Los slugs son la clave pública de los proyectos (no el ID interno).
- No exponer campos internos (`user_id`, `created_by`, campos de auditoría).

### Reglas de media

- Las conversiones deben ser WebP: `thumb` (300×200), `optimized` (1200×1200), `gallery` (1200×800).
- Usar `spatie/laravel-medialibrary` disco `public`.
- Si se regeneran conversiones: `php artisan website:regenerate-media`.

### Reglas de webhook

- El evento GitHub correcto es `backend_update` (no `update_portfolio` — ese era el bug WEB-001).
- El job `NotifyAstroFrontendJob` se dispara desde `ProjectObserver` y `MediaObserver`.
- Si el webhook falla, el build de Astro no se actualiza. Hay que monitorear.

### Backfill pendiente en producción

```bash
php artisan website:regenerate-media
```

### Archivos clave
```
Modules/Website/Jobs/NotifyAstroFrontendJob.php
Modules/Website/Observers/ProjectObserver.php
Modules/Website/Observers/MediaObserver.php
Modules/Website/Models/Project.php            ← conversiones WebP
Modules/Website/Repositories/EloquentProjectRepository.php
Modules/Website/Routes/api.php
```

---

## Módulo Intelligence

### Reglas

- Siempre usar `GeminiService` para llamadas a Gemini. No instanciar el cliente HTTP directamente.
- El Semantic Cache usa hash MD5 del payload. Verificar que el payload es determinista antes de llamar.
- Las operaciones de sync solo deben ejecutarse via commands o jobs, no inline en controllers.
- `SyncMirrorDataService` escribe en MySQL (tablas mirror). Verificar que no se confunde con las tablas del ERP.

### Archivos clave
```
Modules/Intelligence/Services/GeminiService.php
Modules/Intelligence/Services/SyncMirrorDataService.php
Modules/Intelligence/Console/Commands/SyncMirrorCommand.php
```

---

## Módulo Performance

### Reglas

- Los modelos Mirror (`MirrorProject`, `MirrorCost`, etc.) son copias locales en MySQL. Son mutables.
- Los modelos Cafca originales siguen siendo ReadOnly.
- El reporte Watchdog se envía a `orelvys.cuellar@claesen-verlichting.be` los lunes por la mañana.
- Los DTOs (`ProjectAiPayload`, `GeminiContextDTO`) deben usarse para normalizar datos antes de enviar a Gemini.

### Archivos clave
```
Modules/Performance/Console/Commands/SendWatchdogReportCommand.php
Modules/Performance/DTOs/ProjectAiPayload.php
Modules/Performance/Models/Mirror/MirrorProject.php
```

---

## Módulo Prospects

### Reglas

- `Prospect` es la fuente de verdad de audiencias. Mailing referencia `prospect_id`.
- No duplicar datos de contacto en Mailing. Si se necesita un campo, agregarlo a `Prospect`.
- Los syncs de federaciones son idempotentes: ejecutar varias veces no debe crear duplicados.
- `SyncHistory` registra el historial de syncs para auditoría.

### Archivos clave
```
Modules/Prospects/Models/Prospect.php
Modules/Prospects/Console/Commands/SyncMasterCommand.php
Modules/Prospects/Jobs/MasterSyncJob.php
```

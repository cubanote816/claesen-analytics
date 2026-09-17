# Riesgos conocidos y deuda técnica — CAFCA Intelligence Hub

> Riesgos abiertos, bloqueantes, deuda técnica y decisiones pendientes.
> Última actualización: 2026-09-17 (F0/P0 del programa multiempresa Electro Bertels)

---

## Bloqueantes activos

### CLA-514 — Falta staging Laravel y hardening del pipeline de deploy antes de producción

**Estado (2026-09-01):** la cadena de código CLA-519 → CLA-529 está **Done** y consolidada en `release/laravel-13-rc1` (`origin`; base técnica certificada durante FASE 0: `c6a5e89`; Draft **PR #9** contra `main`). `.github/workflows/tests.yml` **existe y está verde**: `Static checks` + `Build front-end assets` + `PHPUnit (PHP 8.4)` = **1322 passed / 0 failed / 0 errors / 2 skipped** (runs `33512861220` / `33526166018` / `33552786969`). La afirmación histórica *"el repositorio no tiene workflow independiente de CI para suite/build/audit"* **quedó obsoleta** desde `tests.yml` (CLA-525).
**Bloqueante que queda:** **no existe un entorno de staging Laravel** — host separado de producción con PHP-FPM 8.4 + Imagick + MySQL 8.4 aislado + workers/scheduler Supervisor. `.github/workflows/deploy.yml` despliega directo a `prod-priv-01` al hacer `push` a `main` (job único `[self-hosted, linux, prod]`). Sin staging no se pueden ejecutar las FASES 1–5 de la certificación (CLA-525) ni el ensayo de rollback previo (CLA-523).
**Riesgos del pipeline (auditoría FASE 0):** el health check de `deploy.yml` hace `curl https://backoffice.claesen.local/` desde el runner → `curl exit 7` (host LAN-only, sin listener alcanzable); `deploy.sh` paso 9 usa `supervisorctl start ... || restart ...` (el `restart` nunca corre sobre un proceso RUNNING → bytecode viejo en workers/scheduler entre deploys, causa raíz del incidente del mirror de Safety); el backup (`paso 0`) continúa aunque falle; no hay rollback automático.
**Acción requerida:** **CLA-530** (aprovisionar staging Laravel real y separado de producción — `blocks` CLA-525) y **CLA-531** (endurecer `deploy.sh`/`deploy.yml` + rollback automático probado en staging — `blocks` CLA-525 y CLA-523) — ambos en Backlog, parent CLA-514. Mantener `backoffice.claesen.local` exclusivamente en LAN; nunca exponerlo para "arreglar" el health check.

### MAI-026 — Webhook handler ESP externo

**Estado:** Bloqueado por decisión de gerencia.
**Descripción:** El módulo Mailing está diseñado para soportar un ESP externo (Resend/Postmark/Mailgun) via `MarketingCampaignInterface`. `SaaSMailer` es el stub listo para implementar. La decisión de qué ESP usar y cuándo migrar está pendiente de gerencia.
**Impacto:** El transporte actual (Microsoft Graph) tiene limitaciones de volumen y deliverability que un ESP externo resolvería. Hasta la decisión, se trabaja con Graph.
**No tocar MAI-026 sin instrucción explícita.**

---

## Riesgos abiertos — Módulo Mailing

### De Mailing se comparte el transporte transaccional, no la plataforma de campañas (decisión 2026-09-17)

**Estado:** decidido a nivel de arquitectura (ADR D11), sin implementar. Mailing sigue funcionando como hoy, 100 % Claesen.

**Alcance real:** Bertels usará el mailer `microsoft-graph` + `MicrosoftGraphTransport` + Laravel Mail para su correo transaccional (aviso interno de lead y confirmación al cliente). **No** usará campañas, audiencias, supresión, plantillas versionadas, A/B ni follow-ups. Por eso **ninguna tabla `mailing_*` recibe `organization_id`**, y en P5b Mailing **sí** entra en la lista de módulos con middleware `organization:claesen` (sus rutas públicas por token son de prospects de Claesen por construcción).

**Trabajo pendiente, en F4/CLA-473:** remitente y nombre visible por sitio — `NewConsultationRequestMail::envelope()` no fija From, así que cae a `config('mail.from.address')`, y `MicrosoftGraphTransport::getPayload()` toma `'name'` de `config('mail.from.name')` **siempre**, de modo que un correo de Bertels mostraría el nombre de Claesen incluso con la dirección corregida; destinatario interno por sitio (`config('website.consultation_notification_email')` es único global, CLA-532); plantilla y marca propias; **la confirmación al cliente no existe todavía**; y permiso de Graph para enviar como el buzón de Bertels (`POST /users/{buzón}/sendMail`).

**Riesgo condicional — solo si algún día se aprueban campañas para Bertels.** Entonces vuelven íntegros estos bloqueantes, todos verificados en código:
- **Audiencia.** `SegmentResolverService` está cableado a `prospects_prospects` (CRM de federaciones de Claesen) y `mailing_messages.prospect_id` apunta ahí: enviar desde Bertels a esa lista sería fuga de datos y uso de un consentimiento ajeno (RGPD).
- **Baja y supresión.** `mailing_suppression_list.email` es UNIQUE global y `config('mailing.unsubscribe_domain')` tiene un único valor (`claesen-verlichting.be`, usado en el `mailto:afmelden@…` de `ProspectCampaignMail`): una baja de Claesen daría de baja de Bertels y al revés.
- **Marca y plantillas.** `campaign.blade.php`/`unsubscribe.blade.php`/`preferences.blade.php` embeden `brand-logo-dark.png` con el alt «Claesen Outdoor Lighting»; `email_templates.name` es UNIQUE global.
- **Acceso.** `CampaignResource::canAccess()` es `auth()->check()`: cualquier usuario con panel vería las campañas de ambas empresas.
- **Destinatarios de alertas.** `CheckDeliverabilityAlertsCommand` notifica por roles globales `super_admin`/`admin`/`campaign_manager`, y `campaign_manager` **no existe** en `RolesAndPermissionsSeeder` (hoy benigno: alcanza solo a los dos primeros).

### Ciclos indirectos en follow-ups

**Riesgo:** Es posible crear un ciclo A → follow-up B → follow-up A. El sistema no lo bloquea.
**Impacto:** Campaña de follow-up que nunca termina, envío infinito a audiencia reducida.
**Mitigación actual:** Ninguna técnica. Es responsabilidad del operador.
**Pendiente:** Validación de ciclos en `SegmentResolverService` o en la UI de Filament al crear follow-ups.

### A/B en SENDING sin substatus visual

**Riesgo:** Una campaña A/B en estado `SENDING` no distingue si está en la fase de split o en la fase de winner seleccionado.
**Impacto:** El operador no puede saber en qué fase está el A/B test mirando el panel.
**Mitigación actual:** Los campos `ab_test_started_at` y `ab_winner_*` permiten inferirlo a nivel de DB.
**Pendiente:** MAI-031 o similar podría añadir substatus visual.

### Enforcement de preferencias de categoría en envío

**Estado:** ✅ Resuelto — MAI-PREF-001 / CLA-161 (2026-06-20)
**Solución implementada:**
- `EmailTemplate.preference_category` (string, nullable): categoría marketing (newsletter/offers/events). TRANSACCIONAL siempre null (hook `saving`).
- `Campaign.template_category_snapshot` + `Campaign.preference_category_snapshot`: capturados en `buildSnapshotFrom()` al seleccionar template, sobreescritos server-side en `transitionTo(APPROVED)`.
- `ExecuteCampaignJob.assertValidSnapshots()`: guard fail-closed — 4 combinaciones exhaustivas antes de cualquier envío.
- Skip order en `sendToProspects()`: unsubscribed → suppression → category_opt_out → no_email → send.
- `List-Unsubscribe` headers condicionales: `ProspectCampaignMail(isCommercial: bool)` — transaccionales no llevan headers de opt-out.
- `mailing:backfill-preference-snapshots` command para campaigns pre-existentes (dry-run por defecto, --apply para commit).
**Pendiente en producción:** Ejecutar `php artisan migrate && php artisan mailing:backfill-preference-snapshots --apply` antes de reiniciar workers (ver secuencia de deploy en el command).

### Fase 3 bloqueada hasta datos reales

**Riesgo:** MAI-031 a MAI-036 (inteligencia sobre campañas) requieren datos históricos reales.
**Condición de desbloqueo:** 4–6 semanas de campañas enviadas en producción.
**No iniciar Fase 3 antes de cumplir esta condición.**

---

## Riesgos abiertos — Módulo Cafca / ERP

### Dependencia de SQL Server legacy

**Riesgo:** El ERP SQL Server (192.168.254.102) es un single point of failure. Si no está disponible, las queries de Cafca fallan.
**Mitigación actual:** `MirrorProject` y otros modelos Mirror en MySQL son el fallback para queries analíticas (implementado en SAF-016 / CLA-51).
**Pendiente:** Ampliar el patrón de fallback a más controladores.

### IDs string sin validación de formato

**Riesgo:** Los IDs del ERP son strings pero no tienen formato documentado. Si cambian de formato, los joins entre tablas podrían fallar silenciosamente.
**Mitigación actual:** `trim()` en todos los modelos Cafca.
**Pendiente:** Documentar el formato esperado de los IDs del ERP.

---

## Riesgos abiertos — Módulo Website

### GitHub token expira o cambia permisos

**Riesgo:** `NotifyAstroFrontendJob` usa un token GitHub para `repository_dispatch`. Si el token expira, el webhook falla silenciosamente (el build de Astro no se actualiza).
**Mitigación actual:** El job loga el error, pero no hay alerta activa.
**Pendiente:** Añadir monitoreo de fallos del job `NotifyAstroFrontendJob`.

### Backfill de media pendiente en producción

**Riesgo:** Las conversiones WebP (WEB-005/WEB-006) requieren ejecutar `php artisan website:regenerate-media` en producción. Si no se ejecuta, las imágenes antiguas no tienen las nuevas conversiones.
**Estado:** Pendiente de ejecutar en producción.
**Acción requerida:**
```bash
php artisan website:regenerate-media
```

---

## Riesgos abiertos — Módulo FieldOps

### Alpine registrado vía @push('scripts')/@once muerto bajo wire:navigate en 4 location-pickers

**Riesgo:** `complex-location-picker.blade.php`, `terrain-location-picker.blade.php`, `structure-location-picker.blade.php` y `electrical-board-location-picker.blade.php` registran su componente Alpine vía `@push('scripts')`/`@once` + `document.addEventListener('alpine:init', ...)`. **Confirmado en vivo (Selenium, no solo por lectura de código) en 2/2 archivos con este mismo anti-patrón auditados hasta ahora**: `luminaire-frame-type-image-editor.blade.php` (CLA-278, commit `a935834`) y `luminaire-frame-spatial-layout.blade.php` (CLA-278, commit `9f2ef37`, este último sin el wrapper `alpine:init` pero con el mismo `@push('scripts')`/`@once` roto). Ambos quedaban completamente inertes (sin datos reactivos, sin listeners, botones/drag/zoom sin responder) al llegar por click dentro del panel (`wire:navigate`, default de Filament) — solo funcionaban con una carga dura de la URL. Con 2/2 confirmados rotos, es razonable asumir que los 4 pickers restantes tienen el mismo problema.
**Estado:** Documentado, sin corregir — decisión explícita del usuario de acotar el alcance de CLA-278 a los archivos que fue encontrando en su propio testing, no a una auditoría preventiva completa. `luminaire-type-gallery-selector.blade.php` se auditó y confirmó que NO tiene este problema (usa `x-data="{...}"` inline, no depende de `alpine:init` ni de un script empujado).
**Fix de referencia:** migrar `@push('scripts')`/`@once` a `@script`/`@endscript` (Livewire) y registrar `Alpine.data(...)` directo (sin envolver en `addEventListener('alpine:init', ...)` si lo tuviera) — ver diffs de los commits `a935834` y `9f2ef37`.
**Acción requerida:** ticket nuevo para auditar y corregir los 4 archivos — dada la tasa de confirmación (2/2), tratar como "muy probablemente roto", no como riesgo especulativo.

### Acceso amplio de roles internos no-cliente a rutas genéricas de FieldOps — DESACTUALIZADO, ver CLA-364/369/377/496

**Estado (corregido 2026-08-28, CLA-496):** la descripción original de este riesgo (abajo, conservada como referencia histórica) predata `hasBroadAccess()` y ya no describe el mecanismo real. Desde CLA-364 (2026-08-08), `EnforceFieldOpsTenantAccess`/`FieldOpsTenantService::scopeForUser()`/`canView()` ya NO hacen un bypass total por "cualquier no-`client`" — el gate real es el permiso Spatie `fieldops.view-all-clients` (`hasBroadAccess()`): `technician` está scoped por diseño desde CLA-364 (necesita `fieldOpsClients` asignados en Filament); `super_admin`/`admin`/`financial_manager`/`hr_manager`/`viewer`/`project_manager` (este último por decisión explícita de CLA-377) mantienen acceso amplio de lectura a propósito.
**Gap real que sí existía y que CLA-496 cierra:** hasta CLA-496, ese mismo permiso amplio de *lectura* (`fieldops.view-all-clients`) era también, de facto, la única barrera para create/update/delete — no existía ninguna capacidad de escritura separada. CLA-496 introduce `fieldops.create`/`fieldops.update`/`fieldops.delete-infrastructure` (más `fieldops.media`/`fieldops.ai`, creados como fundación para CLA-498/CLA-502) sobre `Complex`/`Terrain`/`Structure`/`LuminaireFrame`/`Luminaire`/`ElectricalBoard`, vía una policy separada (`FieldOpsInfrastructurePolicy`) — `financial_manager`/`hr_manager`/`viewer` conservan la lectura amplia pero ya no pueden mutar infraestructura pese a tenerla.
**`ClientPortalInfrastructureController`** sigue exigiendo `isClientUser()` correctamente, sin relación con este cambio.

---

<details>
<summary>Descripción original (2026-08-07, desactualizada — conservada solo como referencia histórica)</summary>

**Contexto:** CLA-344/CLA-345 (auditoría de auth del Client Portal, 2026-08-07). `EnforceFieldOpsTenantAccess` hace bypass total de scoping por tenant para cualquier usuario autenticado sin rol `client` (`if (! $user || ! $this->tenants->isClientUser($user)) { return $next($request); }`). Esto significa que `/api/v1/fieldops/complexes`, `/terrains`, `/structures`, `/luminaire-frames`, `/electrical-boards`, `/clients` devuelven datos de **todos** los clientes sin scope a cualquier usuario interno (Safety PWA, Sport, backoffice), sin distinguir por permiso/rol específico.
**Decisión tomada:** no tocar — Safety PWA/Sport dependen legítimamente de ver todos los clientes en esas mismas rutas; forzar `scopeForUser` incondicional las rompería.
**Pendiente (fuera de alcance de CLA-344/345):** si en el futuro se requiere granularidad de permisos entre roles internos (ej. un `field_technician` no debería ver clientes fuera de sus asignaciones), es un rediseño de RBAC interno más amplio, no un fix puntual de FieldOps.

</details>

---

## Riesgos abiertos — Programa multiempresa (Electro Bertels)

> Hallazgos de la auditoría F0/P0 (2026-09-17). Diseño y secuencia: `docs/ai/adr-multi-organization.md`.
> **Regla de hierro:** hasta que la fase P5 (enforcement) esté completa y verificada, **no debe existir ningún usuario real de Electro Bertels**. Los riesgos 1 y 4 se materializan en el momento en que un usuario de Bertels recibe cualquiera de los roles actuales.

### 1. CRÍTICO — Los roles de Spatie son globales, así que un rol basta para entrar a Claesen

`config/permission.php` tiene `teams => false` y los permisos cuelgan de los roles. `User::hasPanelAccess()` decide el acceso al backoffice solo por rol (`super_admin`, `admin`, `financial_manager`, `hr_manager`, `viewer`), y `admin` arrastra `fieldops.view-all-clients`. Un usuario de Bertels con rol `admin` vería el panel de Claesen, `/api/v1/fieldops/*` completo, `/api/v1/safety/*` y `/api/v1/employees/*`.
**Control:** organización explícita en el usuario + `canAccessPanel`/`EnsurePanelAccess` por panel + middleware de organización en las rutas de los módulos de Claesen (fases P2/P5a/P5b).

### 2. CRÍTICO — La API pública de Website no conoce el sitio

`/v1/website/projects`, `/categories`, `/years` y `/{slug}` devuelven todos los `website_projects` publicados, sin noción de sitio (verificado: no hay ninguna referencia a `site_id` en rutas, controlador, `PortfolioService` ni repositorio). Un proyecto de Bertels aparecería en la web de Claesen.
**Control:** `site_id` + rutas legacy ligadas explícitamente al sitio de Claesen (P3) antes de crear el primer proyecto de Bertels.

### 3. CRÍTICO — `Gate::before` concede todo a `super_admin`

`app/Providers/AppServiceProvider.php:46` devuelve `true` para cualquier ability. En Laravel 13, un valor no nulo en `Gate::before` **es** el resultado: ninguna policy puede denegar acceso cruzado a `super_admin`.
**Control:** frontera de organización antes del privilegio, y lista explícita de abilities de plataforma (P5c, decisión D5 del ADR).

### 4. CRÍTICO — Los destinatarios de notificaciones se eligen por rol global

Siete puntos sin filtro de organización: `MaintenanceRequestService.php:514`, `MaintenanceRequestAlertService.php:131`, `CheckSafetyComplianceCommand.php:32`, `ChecklistObserver.php:26`, `InspectionReminderService.php:39`, `InspectionController.php:138`, `CheckDeliverabilityAlertsCommand.php:156`. Datos operativos de Claesen llegarían por email y en la campana a usuarios de Bertels con esos roles.
**Control:** filtro por organización en cada consulta de destinatarios (P5d).

### 5. CRÍTICO — La publicación del sitio estático es un singleton global

`PublicationState::current()` usa `find(1)` y `config/static_site.php` define un único `webhook_url`/`webhook_secret`. Publicar un registro de Bertels reconstruiría el sitio de Claesen.
**Control:** estado, webhook y secreto por sitio; jobs con `siteId` (P3).

### 6. CRÍTICO — Media privada de FieldOps accesible solo con rol de panel

`GET /fieldops/media/{media}` está protegida por `auth` + `EnsurePanelAccess` (rol), sin comprobar propietario. Los ids son secuenciales.
**Control:** middleware de organización + resolución del propietario (P5b/P5c).

### 7. ALTO — `/api/v1/employees/*` solo exige `auth:sanctum`

**Preexistente, no introducido por el programa multiempresa:** cualquier token válido (incluido el de una cuenta `client`) puede leer horas, rankings y detalles de proyectos del ERP. Con usuarios de Bertels el alcance empeora.
**Control:** organización + rol en ese grupo de rutas (P5b). Conviene un ticket propio por ser un gap actual de Claesen.

### 8. ALTO — Otros vectores pendientes de cierre

- Los recursos Website (`ProjectResource`, `ConsultationRequestResource`) no declaran `canAccess()`: cualquier rol con panel edita proyectos públicos y leads (preexistente).
- El selector «Asignado a» de las solicitudes lista todos los usuarios de la base de datos.
- `AzureRoleService` asigna `viewer` como fallback, y `viewer` está en la lista de acceso al panel.
- `POST /v1/website/consultations` y `/contact-email` no tienen throttle ni antispam, y el segundo crea además un `Prospect` en el CRM de Claesen.
- Fuga de contexto entre jobs en workers de larga vida si `OrganizationContext` se registrara como `singleton` (el ADR lo fija como `scoped`).
- Viabilidad del doble panel (login compartido, callback de Azure, SPA entre paneles, middleware persistente de Livewire): requiere spike en P6.

### 9. MEDIO — Constraints y datos a revisar antes del enforcement

- `website_projects.slug` es único global: debe pasar a `UNIQUE(site_id, slug)`, junto con la regla `->unique()` y el auto-slug de `ProjectResource`.
- `WEBSITE_CONSULTATION_EMAIL` es un único destinatario global de avisos de lead.
- Los originales de media de Website viven en el disco `public`; el plan exige original privado y variantes publicadas para Bertels.
- `website_messages` no tiene ningún consumidor enrutado (`WebsiteController` no está en rutas): clasificado como **UNCERTAIN**, no se toca sin decisión.
- No hay MFA configurado en el panel (Filament 5 lo soporta de forma nativa); es gate previo al primer login real de Bertels.

---

## Deuda técnica

### ~~`env()` en runtime fuera de `config/*.php` — incompatible con `config:cache`~~ RESUELTO (CLA-532, 2026-09-09)

`deploy.sh` corre `php artisan optimize` + `config:cache`; cuando la config está cacheada Laravel **no parsea `.env`** en runtime y producción no exporta esas claves como variables de proceso PHP-FPM, así que todo `env()` en Services/Jobs/Commands/Providers/Blade devuelve su default o `null`. GO-C de CLA-530 confirmó efectos reales: `MAILING_DRIVER=saas`/`MAIL_TO_ADDRESS` ignorados (campañas envían de verdad, sin red de seguridad de destinatario), `AZURE_GROUP_*` colapsados a la clave `''` → todo usuario Azure sin roles previos cae a `viewer`, destinatarios Watchdog fijados a `gerencia@claesen.be`, y `MicrosoftGraphService::__construct` asignando `null` a `string $clientId` → `TypeError` no capturado por los `catch (\Exception)` de la cadena (500 tras persistir en el submit de consulta Website). **CLA-532** migra los usos con impacto a rutas `config()` cache-safe (`app.mailing_driver` fail-closed sin default, `mail.always_to`, `core.azure_role_mapping` null-safe, `performance.watchdog.*`, `website.consultation_notification_email`), añade un `SimulationMailer` explícito (cero HTTP, prohibido en producción), hace que Graph sin credenciales falle con `MailConfigurationException` (nunca `TypeError`) y garantiza HTTP 201 en el endpoint de consulta ante mala configuración. Fuera de alcance de CLA-532: una regla repo-wide que impida `env()` en runtime (los usos restantes — `FrontendRedirectService:48`, cadenas `GEMINI_API_URL` — degradan a un valor válido), y el `env()` de `NotifyAstroFrontendJob` (código muerto, `@deprecated`, no se despacha).

**Cierre (2026-09-09):** implementado en `d058c2b`, más `4511106` (colisión de un helper de test con el método `final TestCase::run()` de PHPUnit 12) y `047dc40` (visibilidad al boot de `MAIL_TO_ADDRESS`/`WEBSITE_CONSULTATION_EMAIL` — `.env.example` los ganó vacíos y CI copia `.env.example`→`.env`; fix solo en tests). Los tres en `release/laravel-13-rc1` por fast-forward. **CI certificada — run `34358992599` sobre `047dc40`**: `Static checks` + `Build front-end assets` success, `PHPUnit (PHP 8.4)` `1355 passed / 0 failed / 0 errors / 2 skipped`. CLA-532 resuelve el bloqueante de configuración de **CLA-530**; el staging sigue sin aprovisionar/certificar y su scheduler sigue STOPPED.

### ~~Casing inconsistente de los directorios de migración de módulo — CLA-527~~ RESUELTO (2026-08-30)

**Detectado en CLA-517, resuelto en CLA-527.** `FieldOps` y `Website` usaban `Database/{Migrations,Factories,Seeders}` (mayúscula) frente a los otros 9 en minúscula; `Safety` tenía además un `Database/Seeders/` residual con un `SafetyDatabaseSeeder` duplicado (stub muerto). CLA-527 renombró los 3 árboles a minúscula (`git mv`, ~96 renames, timestamps intactos → orden de migración idéntico), añadió los mapeos PSR-4 explícitos en `composer.json` (`Modules\FieldOps\Database\Factories\` etc. → rutas minúscula; namespaces `Database\` studly sin cambio), corrigió los 2 providers y 3 tests con rutas hardcodeadas, y borró el stub duplicado. Post-rename los 11 módulos son consistentes: el `auto-discover.migrations` (default nwidart, activo) encuentra a los 11 y el `loadMigrationsFrom` manual apunta a la misma ruta → el migrator deduplica, sin doble registro para nadie. Verificado: `migrate` completo 179 migraciones / 0 duplicados; autoload resuelve todas las clases `Database\*` desde las rutas minúscula. Consolidar a un único call site (quitar los 11 `loadMigrationsFrom` manuales o desactivar auto-discover) sigue siendo un refactor aparte, no hecho en CLA-527.

### Suite FieldOps amplia contaminada entre clases

La ejecución conjunta de toda la suite FieldOps mantiene dos fallos de harness preexistentes: varios `setUp()` usan `Role::create('super_admin')` y chocan con estado compartido (`RoleAlreadyExists`), y los tests de media pueden encontrar directorios de `storage/framework/testing/disks` creados con permisos incompatibles. En el hardening de CLA-267 la corrida amplia terminó con **209 passed / 649 assertions y 93 fallos** de esas dos familias; la regresión integrada aislada pasó **42/42 con 301 assertions** y los tests nuevos también pasan dentro de la corrida amplia. Pendiente normalizar roles con `firstOrCreate`/limpieza del PermissionRegistrar y los permisos del storage de testing en un ticket de infraestructura de pruebas; no mezclar ese refactor con tickets funcionales.

Además, no pasar varios paths de test a `sail artisan test` en este harness: pueden ejecutarse como procesos separados contra la misma base MySQL `testing` y competir durante `RefreshDatabase`. Usar un único proceso con `--filter='(ClaseA|ClaseB)'` o ejecutar cada archivo de forma serial.

### Infraestructura del portal cliente pendiente

El roadmap maestro fija `client.claesen-verlichting.be` como hostname objetivo y descarta el typo previo `clent.claesen-verlichting.be`. El dominio todavía no está provisionado: antes del despliegue se deben verificar DNS, TLS, reverse proxy, redirects OAuth, CORS, cookies y `SANCTUM_STATEFUL_DOMAINS`. Ver `docs/ai/fieldops-maintenance-roadmap.md`; el portal no debe considerarse desplegable solo porque el aislamiento backend esté listo.

**Nota CLA-344/345 (2026-08-07):** confirmado que la cookie de sesión de Sanctum pertenece al dominio del backend, no al de cada frontend — `config/cors.php` permite varios orígenes de frontend contra las mismas rutas `api/*`/`v1/*` con `supports_credentials: true`. Una sesión creada en Safety PWA/Sport es válida para llamadas hechas desde el Client Portal. CLA-344 cierra el login (nadie puede autenticarse *directamente* en el Client Portal sin rol `client`), pero no aísla la sesión entre apps a nivel de cookie — eso requeriría dominios de backend separados por app, fuera de alcance actual. Tenerlo en cuenta al verificar `SANCTUM_STATEFUL_DOMAINS`/`SESSION_DOMAIN` antes del despliegue de producción.

### ~~Test roto: ClientPortalInfrastructureTest duplica código 'soccer' entre dos topologías~~ RESUELTO (CLA-528, 2026-08-30)

`test_client_portal_returns_only_the_members_authorised_topology_and_reduced_payload` llamaba a su helper `topology()` dos veces en el mismo test, y `topology()` creaba `TerrainType`/`StructureType` con `code` hardcodeado (`'soccer'`/`'conical'`) sin `firstOrCreate` — la 2ª llamada chocaba con el `unique` de `fo_terrain_types.code` (`UniqueConstraintViolationException`). CLA-528 lo corrigió: `TerrainType::firstWhere('code', 'soccer') ?? TerrainType::factory()->create(...)` (ídem `StructureType`) — reutiliza la fila de catálogo en la 2ª llamada, conservando la población completa de la factory en la 1ª. Verificado en la suite completa verde (1322/0/0/2).

### Tests de módulo Website inexistentes

Los módulos Safety y Mailing tienen suites de tests completas. El módulo Website no tiene tests Feature documentados en `Modules/Website/tests/`. Cualquier cambio en Website se valida solo manualmente.

### Sin tests para Intelligence y Performance

Los módulos Intelligence y Performance no tienen tests Feature explícitos en `Modules/Intelligence/tests/` ni `Modules/Performance/tests/`. Los servicios IA dependen de Gemini (servicio externo) lo que dificulta el testing sin mocks adecuados.

### Resend instalado pero no usado

`resend/resend-laravel ^1.1` está en `composer.json` pero `SaaSMailer` (que debería usarlo) es un stub vacío. La dependencia está preparada para MAI-026.

### Filament Cluster de Website en app/ en lugar de módulo

Los resources de Website (`ConsultationRequestResource`, `ProjectResource`) están en `app/Filament/Clusters/Website/` en lugar de dentro del módulo `Modules/Website/`. Inconsistencia arquitectónica menor.

---

## Decisiones pendientes

| Decisión | Contexto | Responsable |
|----------|----------|-------------|
| Cuál ESP externo usar (Resend/Postmark/Mailgun) | MAI-026 — transporte email | Gerencia |
| Cuándo iniciar Fase 3 de Mailing | Requiere 4–6 semanas de datos reales en producción | Orelvys |
| Enforcement de preferencias de categoría en envío | Actualmente no bloqueado técnicamente | Equipo técnico |
| Añadir monitoreo de NotifyAstroFrontendJob | Fallos silenciosos si token GitHub expira | Equipo técnico |
| Confirmar hostname del portal cliente | Configuración OAuth/CORS/Sanctum de `CLIENT_PORTAL_URL` | Orelvys |
| ~~¿Electro Bertels usa algún módulo hoy de Claesen?~~ **Resuelta 2026-09-17: solo Mailing, como sistema de envío** (ADR D11) | Mailing pasa a propiedad por fila; el resto sigue exclusivo de Claesen | — |
| ~~¿Campañas o solo transaccional?~~ **Resuelta 2026-09-17: solo transaccional por ahora** (ADR D11) | El tramo Mailing se reduce a F4/CLA-473 | — |
| ¿Fuente de audiencia de Bertels? **Aparcada** mientras no haya campañas; no puede ser `prospects` | Solo una futura decisión de campañas (ADR D11) | Orelvys |
| ¿El correo de `electrobertels.be` está en el mismo tenant de Microsoft 365 que Claesen? | Única decisión de infraestructura para el correo transaccional de Bertels; si no, credenciales por organización o ESP (ADR D11, pregunta 10) | Orelvys |
| Proveedor de identidad de los usuarios de Bertels (mismo tenant Azure, otro tenant o email/contraseña) | Bloquea P5a y la decisión de MFA | Orelvys |
| Vía de acceso del personal de Bertels al backoffice (hoy LAN + túnel) | Bloquea P6 | Orelvys |
| Roles y responsable de administrar los usuarios de Bertels en la primera entrega | Bloquea P6 | Orelvys |
| Identidad remitente y proveedor de correo de Bertels (SPF/DKIM/DMARC) | Bloquea F4 | Orelvys |
| MFA del panel: Azure SSO/MFA, MFA nativo de Filament o ambos | Gate previo al primer login real de Bertels (ADR D8) | Orelvys |
| Autorizar consulta de conteo de solo lectura en producción | Dimensionar los backfills de P2/P3 | Orelvys |
| Assets de marca aprobados para el panel de Bertels | Bloquea P6 | Orelvys |

---

## Cómo actualizar este documento

Añadir un nuevo riesgo cuando:
- Se descubre un bug en review que no se corrige inmediatamente
- Se toma una decisión de dejar algo para más adelante (conscientemente)
- Se bloquea un ticket sin fecha de resolución
- Se detecta una inconsistencia entre módulos que no es crítica

Eliminar o marcar como resuelto cuando:
- El riesgo ya no existe (se implementó la solución)
- Se tomó la decisión que estaba pendiente
- El bloqueante se levantó

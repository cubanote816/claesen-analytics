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

**Tenant de Microsoft 365 compartido (2026-09-17):** `electrobertels.be` está en el mismo tenant que Claesen, así que el envío transaccional de Bertels usa la misma app registration — sin credenciales por organización ni ESP. Pero eleva dos riesgos reales:
- **`Mail.Send` de aplicación sin acotar permite enviar como cualquier buzón del tenant.** Preexistente, pero con dos empresas dentro un error en la resolución del From mandaría correo *como Claesen* desde un flujo de Bertels. Mitigación: Application Access Policy de Exchange limitada a los buzones previstos; From siempre desde configuración del sitio, nunca desde entrada del usuario. Verificar el permiso actual antes de añadir el buzón de Bertels.
- **`AzureRoleService` asigna `viewer` cuando ningún grupo de Azure coincide, y `viewer` está en la allowlist del panel.** Con el tenant compartido, un empleado de Bertels que entre por Microsoft aterrizaría hoy en el panel de **Claesen**: deja de ser hipotético. P5a (`canAccessPanel` por organización + redirección al panel propio) pasa a ser prerrequisito duro del alta de usuarios de Bertels.

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

## Riesgos abiertos — Módulo Prospects

### Fuentes de datos de federaciones sin API oficial (Hockey/TPV/VAL/LBFA)

**Riesgo:** a diferencia de RBFA (GraphQL oficial), AFT/AFTT (PDF publicado) y Bruselas (CSV open-data, CLA-535), las federaciones Hockey belga, TPV, VAL y LBFA no tienen ninguna API pública — sus comandos (`SyncHockeyClubsCommand`/`SyncTpvClubsCommand`/`SyncValClubsCommand`/`SyncLbfaClubsCommand`) siguen haciendo scraping HTML inline, sin adapter `FederationDataSource` propio. Un cambio de layout en cualquiera de esos sitios rompe el sync correspondiente sin aviso más allá de los logs de `SyncHistory`.
**Mitigación actual:** CLA-535 Slice A/B añadió logging de error por-club y `guardedSync()` (ciclo de vida instrumentado) a los 4 comandos, así que un fallo ya no queda silencioso — pero no resuelve la fragilidad estructural del scraping en sí.
**Pendiente:** no hay ticket abierto para migrar estos 4 a un adapter propio — evaluar solo si el negocio confirma degradación real de estas fuentes.

### AFPadel (padel valón) no cubierto — mezclado históricamente con AFT/AFTT

**Riesgo:** `AfttPdfSource` (CLA-535 Slice C) cubre tenis (AFTT) vía el annuaire PDF oficial, pero el padel valón (AFPadel, `afpadel.be`) es una federación separada, solo accesible vía scrape HTML — nunca tuvo un adapter ni un comando propio; el comando `prospects:sync-aft` histórico solo cubría tenis pese al nombre genérico "AFT".
**Estado:** documentado como gap conocido, sin ticket — investigado en la research de CLA-535 (`openspec/changes/prospects-federation-refactor/research.md`), fuera de alcance de la implementación (7 slices ya cerrados).
**Acción requerida:** ticket nuevo si el negocio prioriza cobertura de padel; requeriría un adapter HTML dedicado (mismo patrón de `FederationDataSource`).

### Verenigingsregister (Flandes) requiere API key — no es un bloqueador de código

**Riesgo:** la API oficial de clubes de Flandes (`publiek.verenigingen.vlaanderen.be`) es JSON-LD y pública en su documentación, pero el acceso completo requiere una API key con integración MAGDA (gubernamental) — no se puede automatizar sin gestión administrativa externa al equipo técnico.
**Estado:** verificado en la research de CLA-535, no implementado — es trabajo operativo (solicitar la key), no una tarea de desarrollo bloqueada por código.
**Pendiente:** decisión de negocio sobre si vale la pena tramitar el acceso; sin eso, Flandes sigue cubierta solo por RBFA/Hockey/TPV/VAL.

### Sport Vlaanderen open-data sin URL de descarga directa confirmada

**Riesgo:** el portal `sport.vlaanderen/kennisplatform/open-data/` existe como fuente secundaria potencial para Flandes, pero no expone una URL de descarga directa en su landing page (a diferencia del CSV de Bruselas o el PDF de AFTT) — requeriría investigación adicional para confirmar si el dataset es descargable de forma estable/programática.
**Estado:** verificado como "portal existe, sin URL confirmada" en la research de CLA-535 — no implementado, no bloqueante para el trabajo ya cerrado (RBFA/AFTT/Bruselas cubren la cadena actual).
**Pendiente:** revisitar solo si Verenigingsregister no es viable y se necesita una fuente alternativa para Flandes.

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

### Alpine en 4 location-pickers — RESUELTO, ver CLA-506

**Estado (corregido, verificado 2026-09-20, CLA-506):** el riesgo descrito abajo (conservado como referencia histórica) ya no describe el código actual. Los 4 archivos — `complex-location-picker.blade.php`, `terrain-location-picker.blade.php`, `structure-location-picker.blade.php` y `electrical-board-location-picker.blade.php` — usan `@script`/`@endscript` con `Alpine.data(...)` registrado directo (sin `alpine:init`), el mismo mecanismo ya confirmado en vivo para `luminaire-frame-type-image-editor.blade.php` y `luminaire-frame-spatial-layout.blade.php`. El fix real llegó en el commit `32ef563` (CLA-342, "Electrical Board hereda coordenadas del padre..."), sin ticket dedicado ni mención en su mensaje de commit — por eso este documento quedó desactualizado y CLA-506 se abrió creyendo que el problema seguía vigente. Verificado por lectura completa de los 4 archivos (sin `@push('scripts')`, sin `@once`, sin `addEventListener('alpine:init', ...)` en ninguno) — no se hizo una nueva pasada de Selenium en vivo porque el patrón es idéntico, carácter por carácter, al ya verificado dos veces; se recomienda un click-through manual antes de tratarlo como blindado al 100%.

**Descripción original (histórica, ya no aplica):** `@push('scripts')`/`@once` + `document.addEventListener('alpine:init', ...)`, confirmado roto en vivo (Selenium) en `luminaire-frame-type-image-editor.blade.php` (CLA-278, commit `a935834`) y `luminaire-frame-spatial-layout.blade.php` (CLA-278, commit `9f2ef37`) — ambos quedaban inertes al llegar por click dentro del panel (`wire:navigate`), solo funcionaban con carga dura de la URL. `luminaire-type-gallery-selector.blade.php` se había auditado y confirmado que NO tenía este problema (usa `x-data="{...}"` inline).

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

### 5. CRÍTICO — La publicación del sitio estático sigue teniendo un único webhook global

**Actualizado (CLA-548, 2026-09-18):** `website_publication_states` ya tiene `site_id` (`UNIQUE`) y `PublicationState::current(?int $siteId = null)` ya no usa `find(1)` — resuelve por `firstOrCreate(['site_id' => ...])`, default a Claesen. El riesgo real no está cerrado por esto: `config/static_site.php` sigue definiendo un único `webhook_url`/`webhook_secret` para toda la instalación, así que publicar un registro de Bertels seguiría reconstruyendo el sitio estático de Claesen — el estado ya es direccionable por sitio, pero el destino del webhook todavía no lo es.
**Control:** webhook y secreto por sitio, jobs con `siteId` explícito (F3/F4, según el ticket CLA-548 lo deja documentado como fuera de su alcance).

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

### ~~`OrganizationsSchemaTest` afirma que Website no tiene `site_id` — quedó desactualizado por CLA-547~~ RESUELTO (2026-09-18)

**Encontrado en CLA-549, corregido el mismo día por instrucción explícita del usuario, commit `00fc2f4`.** `Modules/Core/tests/Feature/OrganizationsSchemaTest::test_no_website_data_is_touched_by_this_migration` (CLA-458/P1, commit `4c3288a`) afirmaba `assertFalse(Schema::hasColumn('website_projects', 'site_id'))` (y lo mismo para `website_consultation_requests`) — cierto en P1, pero **CLA-547 (P3a) agregó exactamente esa columna** a ambas tablas sin que nadie actualizara este test, porque el cierre de CLA-547 solo corrió `Modules/Website/tests` + `ClaesenBaseline`, no la suite completa de `Modules/Core/tests` donde vive este archivo. Renombrado a `test_this_migration_never_added_an_organization_id_column_to_website` y las 2 aserciones sobre `site_id` invertidas a `assertTrue` (declarado); las 2 sobre `organization_id` se conservan sin cambios, siguen siendo ciertas (D3: nunca se agrega a tablas site-owned). Verificado: `OrganizationsSchemaTest` 14/14; `Modules/Core/tests` completo **158/158, 0 fallos** (antes 156/158 — el otro fallo, `AccessAnalyticsTest`/`ViteManifestNotFoundException`, se resolvió de paso al construir los assets de Vite en la misma sesión, `npm ci && npm run build`).

### `core:link-users-to-employees` nunca estuvo registrado como comando real (encontrado en CLA-459, 2026-09-18)

`Modules/Core/Providers/CoreServiceProvider.php::registerCommands()` no tiene auto-discovery de directorios — cada comando de consola debe listarse explícitamente en `$this->commands([...])`. Antes de CLA-459 esa lista solo tenía `QaResetEnvironmentCommand`. `LinkUsersToEmployeesCommand.php` (CLA-171, documentado en `CLAUDE.md` → "Sprint User Provisioning" con el comando `php artisan core:link-users-to-employees --dry-run`) **nunca se agregó a esa lista** — `php artisan list` confirma que no existe como comando invocable, desde que se creó. El backfill de producción documentado en `CLAUDE.md` nunca pudo haberse ejecutado tal como está escrito. Encontrado al registrar `BackfillUserOrganizationsCommand` (CLA-459) y verificar primero que el patrón de registro explícito seguía siendo necesario. **No corregido en CLA-459** (fuera de su alcance) — fix trivial: una línea en `registerCommands()`. Sin ticket todavía.

### ~~`env()` en runtime fuera de `config/*.php` — incompatible con `config:cache`~~ RESUELTO (CLA-532, 2026-09-09)

`deploy.sh` corre `php artisan optimize` + `config:cache`; cuando la config está cacheada Laravel **no parsea `.env`** en runtime y producción no exporta esas claves como variables de proceso PHP-FPM, así que todo `env()` en Services/Jobs/Commands/Providers/Blade devuelve su default o `null`. GO-C de CLA-530 confirmó efectos reales: `MAILING_DRIVER=saas`/`MAIL_TO_ADDRESS` ignorados (campañas envían de verdad, sin red de seguridad de destinatario), `AZURE_GROUP_*` colapsados a la clave `''` → todo usuario Azure sin roles previos cae a `viewer`, destinatarios Watchdog fijados a `gerencia@claesen.be`, y `MicrosoftGraphService::__construct` asignando `null` a `string $clientId` → `TypeError` no capturado por los `catch (\Exception)` de la cadena (500 tras persistir en el submit de consulta Website). **CLA-532** migra los usos con impacto a rutas `config()` cache-safe (`app.mailing_driver` fail-closed sin default, `mail.always_to`, `core.azure_role_mapping` null-safe, `performance.watchdog.*`, `website.consultation_notification_email`), añade un `SimulationMailer` explícito (cero HTTP, prohibido en producción), hace que Graph sin credenciales falle con `MailConfigurationException` (nunca `TypeError`) y garantiza HTTP 201 en el endpoint de consulta ante mala configuración. Fuera de alcance de CLA-532: una regla repo-wide que impida `env()` en runtime (los usos restantes — `FrontendRedirectService:48`, cadenas `GEMINI_API_URL` — degradan a un valor válido), y el `env()` de `NotifyAstroFrontendJob` (código muerto, `@deprecated`, no se despacha).

**Cierre (2026-09-09):** implementado en `d058c2b`, más `4511106` (colisión de un helper de test con el método `final TestCase::run()` de PHPUnit 12) y `047dc40` (visibilidad al boot de `MAIL_TO_ADDRESS`/`WEBSITE_CONSULTATION_EMAIL` — `.env.example` los ganó vacíos y CI copia `.env.example`→`.env`; fix solo en tests). Los tres en `release/laravel-13-rc1` por fast-forward. **CI certificada — run `34358992599` sobre `047dc40`**: `Static checks` + `Build front-end assets` success, `PHPUnit (PHP 8.4)` `1355 passed / 0 failed / 0 errors / 2 skipped`. CLA-532 resuelve el bloqueante de configuración de **CLA-530**; el staging sigue sin aprovisionar/certificar y su scheduler sigue STOPPED.

### ~~Casing inconsistente de los directorios de migración de módulo — CLA-527~~ RESUELTO (2026-08-30)

**Detectado en CLA-517, resuelto en CLA-527.** `FieldOps` y `Website` usaban `Database/{Migrations,Factories,Seeders}` (mayúscula) frente a los otros 9 en minúscula; `Safety` tenía además un `Database/Seeders/` residual con un `SafetyDatabaseSeeder` duplicado (stub muerto). CLA-527 renombró los 3 árboles a minúscula (`git mv`, ~96 renames, timestamps intactos → orden de migración idéntico), añadió los mapeos PSR-4 explícitos en `composer.json` (`Modules\FieldOps\Database\Factories\` etc. → rutas minúscula; namespaces `Database\` studly sin cambio), corrigió los 2 providers y 3 tests con rutas hardcodeadas, y borró el stub duplicado. Post-rename los 11 módulos son consistentes: el `auto-discover.migrations` (default nwidart, activo) encuentra a los 11 y el `loadMigrationsFrom` manual apunta a la misma ruta → el migrator deduplica, sin doble registro para nadie. Verificado: `migrate` completo 179 migraciones / 0 duplicados; autoload resuelve todas las clases `Database\*` desde las rutas minúscula. Consolidar a un único call site (quitar los 11 `loadMigrationsFrom` manuales o desactivar auto-discover) sigue siendo un refactor aparte, no hecho en CLA-527.

### Suite FieldOps amplia contaminada entre clases — RESUELTO, ver CLA-507

**Estado (corregido y verificado 2026-09-20, CLA-507):** el riesgo descrito abajo (conservado como referencia histórica) ya no describe el estado real. `--testsuite=Modules --filter=FieldOps` corre **540/540, 2218 assertions**, en 3 corridas seriales consecutivas (2 como root vía `docker exec` sin `-u`, 1 como `sail` vía el wrapper de Sail) — sin un solo fallo de `RoleAlreadyExists` ni de otro tipo. Los 28 archivos de test del módulo ya usan `Role::firstOrCreate(...)` (verificado por grep, cero usos de `Role::create(...)` sin protección) — este ítem del alcance original ya estaba resuelto antes de este ticket, probablemente de forma incremental en tickets posteriores a CLA-267 sin que se actualizara esta nota.

**Causa raíz real encontrada (no la que asumía el ticket):** no era contención de roles ni de `RefreshDatabase` — era que las corridas de `phpunit` vía `docker exec <container> ...` sin `-u sail` caen por defecto en `root` (la imagen no fija `USER`, verificado con `docker inspect`), mientras el proceso real de Laravel dentro del contenedor corre como `sail`. Un test que escribe a disco real durante una corrida como root deja directorios `root:root` bajo `storage/framework/testing/disks/`, que `sail` ya no puede borrar ni sobrescribir en una corrida posterior — permission-denied que parece intermitente. Se encontraron y limpiaron 2 directorios así (`local/2`, `public/luminaire-frame-types`) al iniciar este ticket. Fix real: siempre invocar `phpunit` como `sail` (`./vendor/bin/sail phpunit ...`, nunca `docker exec` a secas) — documentado en `docs/ai/testing-checklists.md`.

**Descripción original (histórica, causa raíz ya no vigente):** se asumía contención de `Role::create('super_admin')` sin protección (`RoleAlreadyExists`) más permisos incompatibles en `storage/framework/testing/disks`. En el hardening de CLA-267 la corrida amplia había terminado con 209 passed / 649 assertions y 93 fallos de esas dos familias.

Nota que sigue vigente: no pasar varios paths de test a `sail artisan test` en este harness — pueden ejecutarse como procesos separados contra la misma base MySQL `testing` y competir durante `RefreshDatabase`. Usar un único proceso con `--filter='(ClaseA|ClaseB)'` o ejecutar cada archivo de forma serial.

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
| ~~¿`electrobertels.be` en el mismo tenant de Microsoft 365?~~ **Resuelta 2026-09-17: sí, lo comparten** (ADR D11) | Sin credenciales por organización ni ESP; exige Application Access Policy y confirmar si los usuarios de Bertels también están en ese tenant | — |
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

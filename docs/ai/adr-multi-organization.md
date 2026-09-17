# ADR — Separación multiempresa Claesen / Electro Bertels en un backoffice compartido

> **Estado:** Aceptado (dirección arquitectónica), 2026-09-17.
> **Tickets Linear:** **CLA-451** (auditoría), **CLA-452** (plan de migración/backfill/rollback) y **CLA-453** (este ADR) — los tres `In Progress`, hito «Fase 0 — Descubrimiento y ADR». **CLA-454** (requisitos no funcionales, RGPD y Definition of Done) sigue en `Backlog`, fuera de este entregable.
> **Fase entregada:** F0 / P0 — auditoría y baseline de regresión. Ninguna fase posterior está autorizada todavía.
> **Fuente de requisitos:** `Plan de implementacion web y backoffice Electro Bertels.docx` (28-08-2026) y el proyecto Linear «Electro Bertels — Web y backoffice multisite».
> **Fuente de verdad sobre la implementación:** este repositorio (`electrobertels/trunk`, base técnica `ef2bc6e`).
> **Baseline de código verificado:** Laravel 13.29.0 · PHP 8.4 · Filament 5.7.6 · Livewire 4.4.2 · Sanctum 4.3.3 · spatie/laravel-permission 8.3.0 (`teams => false`) · MediaLibrary 11.23.5 · Activitylog 5.1.0 · nwidart/laravel-modules 13.0.0 · PHPUnit 12.5.34. CI de referencia: **1355 passed / 0 failed / 2 skipped** (run `34358992599`).

---

## 1. Contexto

El backoffice es un monolito modular con **un único panel Filament** (`admin`, `path('')`), 11 módulos, 34 recursos, 15 páginas propias, 9 widgets, 383 rutas y 179 migraciones. **No existe ningún concepto de organización**: todo pertenece a Claesen de forma implícita.

El aislamiento que sí existe (`FieldOps`: `FoClient` + `fo_client_user` + `FieldOpsTenantService`) separa **clientes de Claesen**, no empresas del grupo. Es un concepto distinto y no se reutiliza como organización.

La asimetría determinante: de los 11 módulos, 7 son de Claesen por naturaleza (Cafca/ERP, Intelligence, Performance, Employee, Prospects, Safety, FieldOps) y **Mailing es el único que se comparte** (decisión del usuario, D11). Lo que Electro Bertels necesita — proyectos, galerías, leads, publicación, usuarios y auditoría — vive casi por completo en el módulo **Website**.

La dificultad principal no son los datos, es la autorización:

- los roles de Spatie son globales (`config/permission.php: teams => false`);
- los permisos van unidos a esos roles (`admin` ⇒ `fieldops.view-all-clients`);
- el acceso al panel se decide solo por rol (`User::hasPanelAccess()`);
- `Gate::before` concede `true` a `super_admin` para cualquier ability (`app/Providers/AppServiceProvider.php:46`).

Consecuencia directa: **un usuario de Bertels con cualquier rol actual entraría al panel de Claesen y a sus APIs**, y recibiría alertas operativas de Claesen. Por eso el orden de trabajo importa más que el diseño.

---

## 2. Decisión

### D1 — Un panel Filament por organización, en la misma aplicación

Una aplicación Laravel, una base de datos, un despliegue y una autenticación. Claesen conserva el panel `admin` en `/` **sin cambios**; Electro Bertels obtiene un panel propio en `/bertels` que registra **solo** recursos conscientes de la organización.

El contexto de empresa queda ligado al **panel (a la URL)**, no a la sesión.

**Alternativas descartadas**

| Alternativa | Motivo del descarte |
|---|---|
| Tenancy nativa de Filament (un panel, URLs `/{tenant}/…`) | Cambia todas las URLs de Claesen (marcadores, deep-links persistidos en `notifications.data`), exige relación de tenant u opt-out en los 34 recursos y no cubre API, jobs, comandos ni media |
| Un panel + empresa activa en sesión | Contexto obsoleto entre pestañas: una acción Livewire de una pestaña podría ejecutarse en el contexto cambiado en otra. Además obliga a ocultar recurso por recurso los ~30 de Claesen |
| Bases de datos o esquemas separados | Duplica despliegue y operación; el documento lo reserva para una fase posterior si divergen políticas o riesgos (§8 del plan) |

### D2 — Pertenencia del usuario: `users.organization_id`

Columna explícita con FK `restrictOnDelete`, nullable durante la transición y **NOT NULL** al final (P7). Garantiza por base de datos que un usuario pertenece a exactamente una empresa.

`super_admin` es un rol de plataforma: tiene organización de casa (Claesen) y accede a la otra solo mediante cambio explícito de contexto.

Se descarta activar **Spatie teams**: obligaría a migrar `roles`/`model_has_roles`/`model_has_permissions` y a fijar `setPermissionsTeamId` en cada request, worker y comando, afectando a las ~80 llamadas `hasRole`/`hasAnyRole` y a toda la batería FieldOps de CLA-496. Riesgo de regresión desproporcionado para dos empresas. Los roles siguen siendo globales: **la organización es la frontera, el rol es la capacidad dentro de ella**.

Se descarta también una tabla `organization_user`: con un usuario por empresa no aporta nada y añade un join. Queda registrada como la evolución natural si algún día un usuario ordinario necesita dos empresas.

### D3 — `site_id` es la fuente de verdad de las entidades del dominio compartido

Las entidades *site-owned* guardan **únicamente `site_id`**; la organización se deriva por `sites.organization_id`.

| Tabla | Columna | Motivo |
|---|---|---|
| `website_projects` | `site_id` | Publicable en un sitio concreto |
| `website_consultation_requests` | `site_id` | El lead nace en un sitio |
| `website_publication_states` | `site_id` (UNIQUE) | Hoy es un singleton `id = 1` |
| `activity_log` | `organization_id` | Hay eventos de plataforma sin sitio |

Dos FKs independientes (`organization_id` + `site_id`) permitirían estados imposibles como *organización Claesen + sitio Bertels*. **Si en el futuro hubiera una razón real de rendimiento o auditoría para desnormalizar `organization_id` en una tabla site-owned, la única forma aceptable es:** índice único en `sites (id, organization_id)` y clave foránea compuesta `(site_id, organization_id) → sites (id, organization_id)`. Nunca dos FKs sueltas.

Las tablas hijas (`website_consultation_activities`, `_reminders`, `_notifications`) no llevan columna: heredan del lead por FK con `cascadeOnDelete`. `media` hereda del modelo propietario.

### D4 — Secuencia obligatoria: estructura → contexto → autorización → enforcement

Nunca «scope fail-closed antes de que exista el contexto». Concretamente:

| Fase | Qué entra | Estado del aislamiento |
|---|---|---|
| P1 | Tablas `organizations` y `sites` | Inactivo (nadie las usa) |
| P2 | `users.organization_id` + backfill + `OrganizationContext` que **solo resuelve** | Inactivo |
| P3 | Columnas `site_id`, relaciones y traits `BelongsToOrganization`/`BelongsToSite` | **Inerte**: el scope consulta `config('organizations.enforce')`; con el flag en `false` no filtra ni lanza |
| P4 | Contexto real en request, Livewire, jobs y auditoría | Inactivo (observable, no restrictivo) |
| P5 | Enforcement: paneles, logins, rutas de módulos de Claesen, `Gate::before`, selects, destinatarios | **Activo** al poner el flag en `true` |

Forma del scope (referencia para P3):

```php
static::addGlobalScope('site', function (Builder $query): void {
    if (! config('organizations.enforce')) {
        return; // P3/P4: estructura sin enforcement
    }

    $siteId = app(OrganizationContext::class)->siteId();

    if ($siteId === null) {
        throw new MissingOrganizationContext(static::class); // P5: falla cerrado
    }

    $query->where($query->getModel()->qualifyColumn('site_id'), $siteId);
});
```

Un scope que filtra en silencio esconde errores; uno explícito se olvida. Con el flag activo falla cerrado, y solo afecta a los modelos del dominio compartido.

### D5 — `Gate::before`: primero frontera de organización, después privilegio

Hoy `Gate::before` devuelve `true` para `super_admin` en cualquier ability, y en Laravel 13 un valor distinto de `null` **es** el resultado de autorización: ninguna policy puede corregirlo. Regla objetivo:

1. Si el usuario no es `super_admin` → `null` (sin cambios).
2. Si el sujeto (`$arguments[0]`, instancia **o** class-string) está en el registro `organizations.owned_models`:
   - pertenece al contexto activo → `true`;
   - no pertenece → `null`, y deciden el scope y la policy (que deniegan).
3. Si **no hay sujeto** → `true` solo si la ability está en la lista explícita `organizations.platform_abilities`; en caso contrario `null`.

«Sin modelo» **no** significa «global». La lista `platform_abilities` arranca vacía: hoy todas las comprobaciones del repositorio pasan un modelo o un class-string (los `FormRequest` de FieldOps usan `can('create', Modelo::class)`), así que el comportamiento de Claesen no cambia. El baseline P0 congela el `Gate::before` actual precisamente para poder demostrarlo.

### D6 — `OrganizationContext` es un binding `scoped`, nunca `singleton`

Se registra con `$this->app->scoped(...)`. El worker de colas reinicia las instancias `scoped` entre jobs (`vendor/laravel/framework/src/Illuminate/Queue/Worker.php:246` invoca el callback `resetScope`), de modo que el contexto no se arrastra de un job al siguiente. Como defensa en profundidad se mantienen el middleware de job (que fija y limpia el contexto en `finally`) y un listener `Queue::after`. Octane no está en uso.

Los jobs reciben **identificadores escalares** (`siteId`, `organizationId`), nunca modelos del dominio compartido: `SerializesModels` restaura los modelos antes de `handle()` y de cualquier middleware, así que un modelo con scope se rehidrataría sin contexto.

### D7 — Migraciones de estructura separadas de migraciones de datos

- `<ts>_create_organizations_and_sites_tables.php` — solo esquema.
- `<ts>_seed_claesen_organization_and_site.php` — solo datos, idempotente (`updateOrInsert` por `slug`/`key`).
- Más adelante, `<ts>_seed_electro_bertels_organization_and_site.php`.

Son migraciones versionadas, **no seeders de despliegue**: `infrastructure/scripts/deploy.sh` ejecuta únicamente `migrate --force` y nunca un seeder (lección de CLA-496).

Los backfills usan `DB::table()` y **nunca modelos**: hacerlo con Eloquent dispararía `ProjectObserver` (webhook de reconstrucción del sitio), `HasAiTranslations` (traducción con Gemini) y `LogsActivity`.

Ninguna migración de este programa borra datos en `down()`. Los `down()` de columnas se niegan (lanzan excepción) si existe cualquier fila de otra organización; el alta de Bertels nunca se revierte borrando: se marca `status = suspended`.

### D8 — MFA es un gate previo al primer usuario real de Bertels

El documento exige 2FA para administradores antes de producción y hoy **el panel no tiene MFA configurado** (solo el MFA propio de Azure para usuarios SSO). Filament 5 incluye proveedores MFA nativos (`Filament\Auth\MultiFactor\App` y `…\Email`, configurables con `multiFactorAuthenticationProviders`), así que el panel puede exigirlo sin desarrollo propio.

Decisión requerida **antes de cerrar P7** (Azure SSO/MFA, MFA del panel, o ambos según el rol). No bloquea P1–P7, sí bloquea el primer login real de un usuario de Bertels en producción.

### D9 — Módulos de Claesen: propiedad declarada a nivel de módulo

`config/organizations.php` declara qué módulos pertenecen a Claesen. Sus grupos de rutas (API y web) reciben un middleware de organización, y sus recursos siguen registrados solo en el panel `admin`. Así se evita añadir `organization_id` a ~100 tablas que nunca tendrán otro dueño, manteniendo la propiedad explícita y verificable por test.

Si Bertels llegara a adoptar alguno de esos módulos (ver preguntas abiertas), ese módulo pasaría a necesitar propiedad a nivel de fila; el resto del diseño no cambia. **Mailing es exactamente ese caso y ya está decidido: ver D11.**

### D10 — Regla de hierro de secuencia

**Hasta que P5 esté completa y verificada no debe existir ningún usuario real de Electro Bertels**, porque los roles globales le darían acceso a datos de Claesen. El alta de la organización y el sitio de Bertels ocurre al final de P7; los proyectos y leads de Bertels, en las fases F3/F4 del documento.

---

### D11 — Mailing es el único módulo compartido (decisión del usuario, 2026-09-17)

Respuesta a la pregunta abierta nº 1: **Electro Bertels usará el módulo Mailing como sistema de envío de correo; ningún otro módulo de Claesen se comparte.** Cafca/ERP, Employee, Intelligence, Performance, Prospects, Safety y FieldOps siguen siendo propiedad exclusiva de Claesen a nivel de módulo (D9).

Compartir Mailing tiene dos capas que **no** se comparten igual:

**Capa de transporte e infraestructura — compartida tal cual.** `App\Contracts\MarketingCampaignInterface`, `MicrosoftGraphTransport`, el pipeline de Laravel Mail, el tracking por token y el parser de NDR son técnicos y neutrales: no necesitan cambios por organización.

**Capa de datos, identidad y audiencia — por organización.** Aquí Mailing deja de ser Claesen-only y pasa a **propiedad por fila**:

| Qué | Estado hoy | Qué exige compartir Mailing |
|---|---|---|
| **Audiencia** | `SegmentResolverService` está cableado a `prospects_prospects` (CRM de federaciones deportivas de Claesen, ~1167 filas) y `mailing_messages.prospect_id` apunta ahí. Los tres `audience_type` (`all_subscribed`/`segment`/`manual`) resuelven contra esa tabla | **Bloqueante de producto.** Bertels no tiene audiencia en `prospects`, y enviar a los prospects de Claesen desde Bertels sería fuga de datos **y** un problema de RGPD: ese consentimiento se dio a Claesen, no a Bertels. Hace falta una fuente de audiencia propia (leads de su sitio con consentimiento, y/o una tabla de contactos propia) antes de la primera campaña |
| **Identidad remitente** | Un único buzón/app registration de Microsoft Graph (`config('mail.mailers.microsoft-graph.*')`) y un único `config('app.mailing_driver')` global con allow-list fail-closed (CLA-532) | El driver y el remitente deben resolverse **por campaña** (derivados de su organización), no de una clave global. Bertels necesita su propio dominio con SPF/DKIM/DMARC; enviar su correo desde el buzón de Claesen degrada la entregabilidad y confunde quién es el responsable del tratamiento |
| **Baja y supresión** | `mailing_suppression_list.email` UNIQUE global; `config('mailing.unsubscribe_domain')` con default `claesen-verlichting.be`, usado en el `mailto:afmelden@…` de `ProspectCampaignMail` | `UNIQUE(organization_id, email)` y dominio de baja por sitio. Darse de baja de Claesen **no** puede dar de baja de Bertels ni al revés: son remitentes y responsables distintos. Una queja de spam sigue siendo permanente, pero contra su propio remitente |
| **Plantillas y marca** | `email_templates.name` UNIQUE global; `campaign.blade.php`, `unsubscribe.blade.php` y `preferences.blade.php` embeden `brand-logo-dark.png` con el alt «Claesen Outdoor Lighting» | `UNIQUE(organization_id, name)` y marca resuelta por organización, igual que la del panel |
| **Campañas, mensajes, eventos, enlaces, preferencias, alertas** | `mailing_campaigns` no tiene ninguna columna de organización | `organization_id` en `mailing_campaigns`, `email_templates`, `mailing_suppression_list`, `mailing_contact_preferences` y `mailing_deliverability_alerts`. `mailing_messages`, `mailing_message_events` y `mailing_tracked_links` heredan de la campaña por FK, sin columna propia |
| **Acceso en el panel** | `CampaignResource::canAccess()` devuelve `auth()->check()` — cualquier usuario con panel ve todas las campañas | Scope por organización + gate de rol; el recurso se registra en ambos paneles |
| **Destinatarios de alertas** | `CheckDeliverabilityAlertsCommand` notifica a los roles globales `super_admin`/`admin`/`campaign_manager` (este último **no existe** en `RolesAndPermissionsSeeder`, así que hoy solo alcanza a los dos primeros) | Filtrado por organización, como el resto del riesgo crítico de destinatarios |
| **Scheduler** | Cinco comandos (`dispatch-scheduled`, `ab-select-winner`, `dispatch-followups`, `parse-bounces`, `check-deliverability-alerts`) procesan todas las campañas | Se mantienen sin contexto ambiental y resuelven la organización **desde la fila de la campaña** — es la forma correcta y no requiere contexto por request |

**Consecuencia inmediata sobre el plan:** en P5b, Mailing **queda fuera** de la lista de módulos que reciben el middleware `organization:claesen`. Ponerlo ahí bloquearía a Bertels justo en el único módulo que sí debe compartir.

**Consecuencia sobre MAI-026:** el stub `SaaSMailer` y la decisión de ESP externo (bloqueada por gerencia) dejan de ser solo una mejora de entregabilidad — pasan a ser una de las dos vías para dar a Bertels una identidad remitente propia; la otra es una segunda app registration de Graph.

Nada de esto entra en P1–P7, que no toca Mailing. El trabajo de Mailing multiempresa es su propio tramo, posterior al enforcement, y arranca por la fuente de audiencia: sin eso, la plataforma compartida no tiene a quién enviar por Bertels.

## 3. Consecuencias

**Positivas**

- Claesen conserva URL, login, navegación, búsqueda global, widgets y contratos de API.
- El aislamiento de la interfaz es estructural: el panel de Bertels no registra los recursos de Claesen, así que no hay nada que ocultar.
- El contexto vive en la URL: desaparece el problema de pestañas con contexto obsoleto.
- Cada fase es aditiva y reversible; el enforcement se activa y desactiva por configuración.

**Negativas y costes aceptados**

- Dos providers de panel que mantener.
- Los recursos del dominio compartido necesitan scoping de fila igualmente.
- Viabilidad técnica por confirmar con un spike en P6: login compartido entre paneles, callback de Azure, navegación SPA entre paneles y middleware persistente de Livewire (`->middleware([...], isPersistent: true)`).
- `super_admin` pierde el bypass universal sobre modelos del dominio compartido fuera del contexto activo. Es intencional.

---

## 4. Riesgos que gobiernan el orden de trabajo

Registrados en `docs/ai/known-risks.md` → «Programa multiempresa». Los críticos:

1. Roles globales: cualquier rol actual abre el panel y las APIs de Claesen.
2. `/v1/website/*` no conoce el sitio: un proyecto de Bertels aparecería en la web de Claesen.
3. `Gate::before` impide denegar a `super_admin` mediante policies.
4. Destinatarios de notificaciones seleccionados por rol global (FieldOps, Safety, Mailing).
5. Publicación singleton: publicar en Bertels reconstruiría el sitio de Claesen.
6. `/fieldops/media/{media}` protegido solo por rol: descarga de media privada de Claesen por id.

---

## 5. Entregable de P0 (esta fase)

Baseline de regresión en `tests/Feature/ClaesenBaseline/`, con snapshots commiteados en `snapshots/`:

| Test | Congela | BD |
|---|---|---|
| `RouteInventorySnapshotTest` | Las 383 rutas: URI, nombre, acción y middleware | No |
| `ScheduleSnapshotTest` | Las 22 tareas programadas: comando, expresión, timezone y guardas | No |
| `PanelRegistrySnapshotTest` | Panel `admin`: id, path, grupos de navegación, 34 recursos (modelo, slug, navegación, título de registro, búsqueda global), 15 páginas, 9 widgets; y que `admin` es el único panel | No |
| `AccessControlContractTest` | Las decisiones de autorización que el programa debe cambiar y los gaps documentados | No |
| `PanelAccessMatrixTest` | Matriz de comportamiento: acceso al panel por rol y `canAccess()` de 34 recursos y 12 páginas para los 5 roles con panel | Sí |
| `WebsitePublicApiContractTest` | Contrato de la API pública: forma del payload, publicados, 201/422 del intake y ausencia de throttle | Sí |

Regeneración deliberada de snapshots: `UPDATE_BASELINE_SNAPSHOTS=1 ./vendor/bin/phpunit tests/Feature/ClaesenBaseline`. Un snapshot nunca se escribe en una corrida normal: si cambia, el test falla y la diferencia debe revisarse en el pull request.

**Verificación de P0:** 4 tests sin BD **21/64 assertions**, 2 con `RefreshDatabase` **56/311 assertions**, suite `Feature` completa **109 tests / 482 assertions, 0 fallos** (más los 4 «PHPUnit Notices» preexistentes de CLA-524). Pint y `php -l` limpios.

**Cómo correr la suite en este worktree:** no hay `.env`, así que `DB_CONNECTION` cae al default `sqlite` del skeleton de Laravel y la corrida migraría a un fichero en la raíz del repo en lugar de a MySQL. Hay que pasar el driver explícito:

```bash
docker compose up -d mysql   # con DB_DATABASE=claesen_analytics_web_testing DB_USERNAME=sail DB_PASSWORD=password FORWARD_DB_PORT=3310
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3310 DB_USERNAME=sail DB_PASSWORD=password \
  APP_KEY="base64:$(head -c 32 /dev/urandom | base64)" ./vendor/bin/phpunit --testsuite=Feature
```

El puerto 3310 evita el 3308 que ocupa otro worktree. `PanelAccessMatrixTest` llama a `withoutVite()` a propósito: el baseline mide autorización, no el pipeline de assets, y sin eso falla con `ViteManifestNotFoundException` en cualquier checkout sin `npm run build` (el fallo ambiental que tumbó 84 tests en la 1ª corrida de CI de CLA-525).

---

## 6. Plan de fases

| Fase | Objetivo | Criterio de salida | Rollback |
|---|---|---|---|
| **P0** | Baseline de regresión + este ADR | Suite verde con los 6 tests nuevos; cero cambios fuera de `tests/` y `docs/` | Revertir commit |
| **P1** | `organizations` + `sites` + alta de Claesen | Tablas y fila de Claesen; snapshots sin diff | `down()` (sin referencias todavía) |
| **P2** | `users.organization_id` + backfill + `OrganizationContext` (solo resuelve) | 0 usuarios sin organización; todos los caminos de creación la fijan | Revertir código; columna inocua |
| **P3** | `site_id` en el dominio compartido, traits inertes, `UNIQUE(site_id, slug)`, publicación por sitio | Contrato de API y matriz de panel idénticos al baseline | Revertir código; `down()` restaura `UNIQUE(slug)` |
| **P4** | Contexto real (request, Livewire persistente, jobs) + `AuditLogger` | Contexto disponible y auditado, sin restringir | Revertir código |
| **P5a–d** | Enforcement por capas: paneles y logins → rutas de módulos Claesen (**Mailing excluido**, D11) → `Gate::before`, policies, selects y búsqueda → asíncrono y destinatarios | Matriz de aislamiento verde con fixture de Bertels; Claesen idéntico con el flag en ambos estados | `ORGANIZATIONS_ENFORCE=false` + `infrastructure/scripts/reload-config.sh` |
| **P6** | Panel Bertels (marca, dashboard vacío) + selector auditado de `super_admin` | Spike confirmado; E2E de cambio de contexto | Quitar el provider de `bootstrap/providers.php` |
| **P7** | `NOT NULL`, flag activo en producción, alta de Electro Bertels, decisión de MFA (D8) | Staging certificado (CLA-530/531/525); matriz verde en CI | Flag en `false`; `NOT NULL → NULL`; Bertels se suspende, no se borra |
| **F3/F4** | Recursos Bertels, API pública por sitio, webhook por sitio, antispam y throttle, originales privados, roles de organización | Por ticket | Por ticket |
| **Mailing multiempresa** (D11, posterior al enforcement) | Fuente de audiencia de Bertels **primero**; luego `organization_id` en campañas/plantillas/supresión/preferencias/alertas, driver y remitente por campaña, dominio de baja y marca por organización, scope y rol en `CampaignResource`, destinatarios de alertas por organización | Por ticket; ninguna campaña de Bertels antes de resolver audiencia e identidad remitente | Por ticket; la supresión nunca se fusiona entre organizaciones |

---

## 7. Preguntas abiertas que bloquean fases concretas

| # | Pregunta | Bloquea |
|---|---|---|
| 1 | ~~¿Electro Bertels usa o usará algún módulo hoy de Claesen?~~ **Respondida (2026-09-17): solo Mailing, como sistema de envío de correo.** Ver D11 | Resuelta. Abre las preguntas 8, 9 y 10 |
| 8 | ¿Bertels necesita **campañas de marketing** o solo **correo transaccional** (confirmación de lead y aviso interno)? Son dos alcances muy distintos: lo transaccional ya lo cubre F4/CLA-473 con remitente por sitio; las campañas exigen todo lo de D11 | Tramo Mailing multiempresa |
| 9 | ¿De dónde sale la **audiencia** de Bertels? No puede ser `prospects` (CRM de federaciones de Claesen: fuga de datos y consentimiento ajeno). Opciones: leads de su propio sitio con consentimiento, una tabla de contactos propia, o importación con base legal documentada | Primera campaña de Bertels |
| 10 | ¿Identidad remitente de Bertels: segunda app registration de Microsoft Graph, o ESP externo (desbloquearía MAI-026)? Requiere dominio propio con SPF/DKIM/DMARC | Tramo Mailing multiempresa; se solapa con la pregunta 5 |
| 2 | ¿Cómo se autentica el personal de Bertels: mismo tenant de Azure AD (¿qué grupos?), otro tenant, o email y contraseña? | P5a, D8 |
| 3 | ¿Cómo accede el personal de Bertels, si `backoffice.claesen.local` es solo LAN + túnel y no debe exponerse a Internet? | P6 |
| 4 | ¿Quién administra los usuarios de Bertels en la primera entrega y con qué roles? | P6 |
| 5 | ¿Qué identidad remitente y proveedor usan los correos de Bertels (SPF/DKIM/DMARC de `electrobertels.be`)? | F4 |
| 6 | ¿Se autoriza una consulta de conteo de solo lectura en producción para dimensionar los backfills? | P2, P3 |
| 7 | ¿Hay assets de marca aprobados para el panel de Bertels? | P6 |

---

## 8. Notas de precisión sobre el mapa de hosts

- `backoffice.claesen.local` — backoffice; **solo LAN + túnel**, nunca expuesto a Internet.
- `backend.claesen-verlichting.be` — host público del túnel que expone la API y el enlace «Website V1 (Demo)» del panel. **No es** el frontend Astro de producción.
- Frontend Astro de Claesen — repositorio `cubanote816/website-claesen-v1`, desplegado por espejo LFTP. Su hostname de producción no está declarado en la configuración versionada de este repositorio (vive en `.env`/secretos de CI): **a confirmar** antes de darlo por bueno en cualquier diagrama.
- `service.claesen-verlichting.be`, `client.claesen-verlichting.be`, `fieldops.claesen-verlichting.be` — PWAs de Claesen (Safety, portal cliente, Sport/FieldOps).
- `electrobertels.be` — web pública de Bertels, repositorio y despliegue independientes (`/home/totti/electrobertels.be`, Astro 7, contrato de backoffice todavía simulado con identidad de sitio `electro-bertels`).

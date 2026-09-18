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

La asimetría determinante: de los 11 módulos, 7 son de Claesen por naturaleza (Cafca/ERP, Intelligence, Performance, Employee, Prospects, Safety, FieldOps) y de Mailing solo se comparte el **transporte transaccional**, no su plataforma de campañas (decisiones del usuario, D11). Lo que Electro Bertels necesita — proyectos, galerías, leads, publicación, usuarios y auditoría — vive casi por completo en el módulo **Website**.

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

### D11 — De Mailing se comparte el transporte transaccional, no la plataforma de campañas (decisiones del usuario, 2026-09-17)

Dos respuestas del usuario, en este orden:

1. De todos los módulos de Claesen, **el único que se comparte es Mailing**, como sistema de envío de correo de Bertels.
2. Alcance: **solo transaccional por ahora** — sin campañas de marketing.

La segunda acota radicalmente la primera, porque en este repositorio el correo transaccional **no pasa por el dominio de Mailing**: `ConsultationService` hace `Mail::mailer('microsoft-graph')->send(new NewConsultationRequestMail(...))`, o sea Laravel Mail más el transporte. Campañas, audiencias, supresión, plantillas versionadas, A/B y follow-ups son otra cosa y quedan fuera.

**Se comparte** (infraestructura, sin cambios por organización): el mailer `microsoft-graph` de `config/mail.php`, `MicrosoftGraphTransport`, `MicrosoftGraphService` y el pipeline de Laravel Mail.

**Sigue siendo exclusivo de Claesen, con propiedad a nivel de módulo (D9):** `mailing_campaigns`, `email_templates`, `mailing_messages`, `mailing_message_events`, `mailing_suppression_list`, `mailing_tracked_links`, `mailing_contact_preferences`, `mailing_deliverability_alerts`, los cinco comandos del scheduler, `CampaignResource`/`EmailTemplateResource` y las rutas públicas de baja, preferencias y tracking (tokens de prospects de Claesen).

**Revisión explícita de la primera versión de esta decisión:**

- **Ninguna tabla `mailing_*` recibe `organization_id`.** No hace falta mientras no haya campañas de Bertels.
- Los tres bloqueantes que había levantado — audiencia cableada a `prospects`, supresión por organización y dominio de baja — **salen del alcance inmediato**. Vuelven íntegros si algún día se aprueban campañas para Bertels; quedan registrados como riesgo condicional en `known-risks.md`.
- En **P5b, Mailing vuelve a la lista** de módulos que reciben `organization:claesen`. Las rutas públicas por token no llevan middleware y son de Claesen por construcción.
- El tramo «Mailing multiempresa» desaparece del plan y se sustituye por el trabajo de **F4 / CLA-473** (correo transaccional y trazabilidad de entrega).

**Lo que sí hace falta para el correo transaccional de Bertels — cuatro puntos, todos pequeños:**

| # | Qué | Estado verificado hoy |
|---|---|---|
| 1 | **Remitente por sitio** | El mecanismo ya existe: `MicrosoftGraphTransport::getSenderEmail()` toma el From del propio mensaje y publica en `/users/{remitente}/sendMail`. Pero `NewConsultationRequestMail::envelope()` **no fija From**, así que hoy cae a `config('mail.from.address')` global; y `getPayload()` toma `'name'` de `config('mail.from.name')` **siempre**, así que incluso con la dirección correcta el nombre visible diría Claesen. Hay que respetar el From del Mailable en ambos campos |
| 2 | **Destinatario interno por sitio** | `config('website.consultation_notification_email')` es una única dirección global (CLA-532) |
| 3 | **Plantilla y marca por organización** | `website::emails.new-consultation-request` es de Claesen. Y **la confirmación al cliente no existe**: hoy solo se envía el aviso interno, así que es trabajo nuevo de F4 — el plan §4 Módulo C sí la exige |
| 4 | **Autorización en Graph** | `POST /users/{buzón}/sendMail` exige que la app registration pueda enviar como ese buzón. Si el correo de `electrobertels.be` vive en el mismo tenant de Microsoft 365, basta con conceder ese permiso; si está en otro tenant o proveedor, hacen falta credenciales por organización (un segundo mailer con nombre propio) o un ESP |

El comportamiento fail-closed de CLA-532 ya es el correcto aquí: sin credenciales de Graph o sin destinatario configurado, la consulta **se guarda igual** y el endpoint devuelve 201 con un `Log::warning`; no hay 500 posterior al commit que obligue al visitante a reintentar.

**Tenant de Microsoft 365 compartido (respuesta del usuario, 2026-09-17).** `electrobertels.be` vive en el mismo tenant que Claesen, así que el punto 4 de la tabla se resuelve por la vía barata: **no hacen falta credenciales por organización, ni un segundo mailer, ni un ESP.** La misma app registration puede enviar como el buzón de Bertels con `Mail.Send` de aplicación. Consecuencia colateral: **MAI-026 vuelve a ser solo una decisión de entregabilidad de las campañas de Claesen**, desacoplada de Bertels.

Dos cosas que el tenant compartido **empeora**, y que hay que tratar como parte del trabajo, no como detalle de configuración:

1. **Alcance del permiso de envío.** `Mail.Send` de aplicación, sin acotar, permite a la app enviar **como cualquier buzón del tenant**. Es preexistente, pero con dos empresas dentro el impacto cambia: un error en la resolución del From mandaría correo *como Claesen* desde un flujo de Bertels, o al revés. Mitigación: **Application Access Policy** de Exchange restringida a un grupo de seguridad con exactamente los buzones que la app puede usar, y el From resuelto siempre desde configuración del sitio, nunca desde entrada del usuario. Verificar el estado actual del permiso antes de añadir el buzón de Bertels.
2. **Login de Azure y el fallback a `viewer`.** Si los usuarios de Bertels también viven en el tenant compartido —lo natural, pero **pendiente de confirmar explícitamente** (pregunta 2)—, entran por la misma app registration. Y `AzureRoleService` asigna `viewer` cuando ningún grupo coincide, rol que **está en la allowlist de acceso al panel**: un empleado de Bertels que entre por Microsoft aterrizaría hoy en el panel de **Claesen**. Esto deja de ser hipotético y convierte P5a (`canAccessPanel` por organización + redirección al panel propio) en prerrequisito duro del alta de usuarios de Bertels, además de exigir grupos de Azure propios de Bertels si sus roles difieren.

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
| **P5a–d** | Enforcement por capas: paneles y logins → rutas de módulos Claesen (**Mailing incluido**: solo se comparte su transporte, D11) → `Gate::before`, policies, selects y búsqueda → asíncrono y destinatarios | Matriz de aislamiento verde con fixture de Bertels; Claesen idéntico con el flag en ambos estados | `ORGANIZATIONS_ENFORCE=false` + `infrastructure/scripts/reload-config.sh` |
| **P6** | Panel Bertels (marca, dashboard vacío) + selector auditado de `super_admin` | Spike confirmado; E2E de cambio de contexto | Quitar el provider de `bootstrap/providers.php` |
| **P7** | `NOT NULL`, flag activo en producción, alta de Electro Bertels, decisión de MFA (D8) | Staging certificado (CLA-530/531/525); matriz verde en CI | Flag en `false`; `NOT NULL → NULL`; Bertels se suspende, no se borra |
| **F3/F4** | Recursos Bertels, API pública por sitio, webhook por sitio, antispam y throttle, originales privados, roles de organización | Por ticket | Por ticket |
| **Correo transaccional de Bertels** (D11 · F4/CLA-473) | Remitente y nombre visible por sitio (respetar el From del Mailable), destinatario interno por sitio, plantilla y marca propias, confirmación al cliente (hoy **no existe**) y permiso de Graph para el buzón de Bertels en el tenant compartido, acotado por Application Access Policy | Por ticket; un lead de Bertels se guarda y responde 201 aunque el correo falle (fail-closed de CLA-532) | Por ticket; el fallback al remitente global de Claesen nunca se restaura en silencio |

**Nota de secuencia (2026-09-18, decisión explícita del usuario):** la porción de "contexto resuelto en servidor y revalidado por request" de P4 se construyó (CLA-460 parcial) antes que P3 (`site_id` en el dominio compartido de Website). Es una excepción deliberada, no un abandono del orden estructura→contexto→autorización→enforcement: `OrganizationContext` resuelve directo desde `users.organization_id` (P2) y nunca toca `site_id`, así que no hay dependencia técnica real sobre P3. El resto de P4 (`AuditLogger`, jobs) y todo P3 siguen sin empezar — este adelanto no se repite implícitamente para otras piezas de P3/P4/P5 sin la misma revisión explícita caso por caso.

---

## 7. Preguntas abiertas que bloquean fases concretas

| # | Pregunta | Bloquea |
|---|---|---|
| 1 | ~~¿Electro Bertels usa o usará algún módulo hoy de Claesen?~~ **Respondida (2026-09-17): solo Mailing, como sistema de envío de correo.** Ver D11 | Resuelta. Abre las preguntas 8, 9 y 10 |
| 8 | ~~¿Campañas de marketing o solo correo transaccional?~~ **Respondida (2026-09-17): solo transaccional por ahora.** Ver D11 | Resuelta. Reduce el tramo Mailing a F4/CLA-473 |
| 9 | ~~¿De dónde sale la audiencia de Bertels?~~ **Aparcada**: no aplica mientras no haya campañas. Si algún día se aprueban, vuelve como bloqueante (la audiencia no puede ser `prospects`: consentimiento dado a Claesen) | Solo una futura decisión de campañas |
| 10 | ~~¿El correo de `electrobertels.be` está en el mismo tenant de Microsoft 365?~~ **Respondida (2026-09-17): sí, lo comparten.** Ver D11 | Resuelta. Elimina la necesidad de credenciales por organización y de ESP |
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

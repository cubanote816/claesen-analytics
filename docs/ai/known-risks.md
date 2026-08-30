# Riesgos conocidos y deuda técnica — CAFCA Intelligence Hub

> Riesgos abiertos, bloqueantes, deuda técnica y decisiones pendientes.
> Última actualización: 2026-08-29 (CLA-515)

---

## Bloqueantes activos

### CLA-515/CLA-514 — Readiness incompleta para Laravel 13

**Estado:** CLA-515 completado; CLA-514 todavía no autoriza actualizar Laravel hasta cerrar sus tickets bloqueantes.
**Riesgo:** el repositorio no tiene workflow independiente de CI para suite/build/audit ni staging versionado. El deploy 106 completó en `prod-priv-01`, pero su health check final terminó con curl exit 7 y dejó el job en rojo; la última ejecución exitosa fue la 95 del 2026-07-07.
**Evidencia:** `docs/ai/laravel-13-readiness.md`; suite completa en base aislada: 1072 passed, 200 failed, 2 skipped. Producción está en PHP CLI 8.4.22/Composer 2.10.1, FPM 8.4 activo y workers/scheduler sobre `/usr/bin/php8.4`.
**Acción requerida:** mantener `backoffice.claesen.local` exclusivamente en LAN y su integración por túnel; nunca resolver el health check exponiéndolo a Internet. CLA-523 debe validar el listener o la ruta interna real. Definir CI/staging o aprobar el baseline/waiver, sanear advisories en CLA-520 y estabilizar la suite en CLA-524 antes del cambio de framework. `php8.4-imagick` ya está instalado y verificado en el host.

### MAI-026 — Webhook handler ESP externo

**Estado:** Bloqueado por decisión de gerencia.
**Descripción:** El módulo Mailing está diseñado para soportar un ESP externo (Resend/Postmark/Mailgun) via `MarketingCampaignInterface`. `SaaSMailer` es el stub listo para implementar. La decisión de qué ESP usar y cuándo migrar está pendiente de gerencia.
**Impacto:** El transporte actual (Microsoft Graph) tiene limitaciones de volumen y deliverability que un ESP externo resolvería. Hasta la decisión, se trabaja con Graph.
**No tocar MAI-026 sin instrucción explícita.**

---

## Riesgos abiertos — Módulo Mailing

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

## Deuda técnica

### ~~Casing inconsistente de los directorios de migración de módulo — CLA-527~~ RESUELTO (2026-08-30)

**Detectado en CLA-517, resuelto en CLA-527.** `FieldOps` y `Website` usaban `Database/{Migrations,Factories,Seeders}` (mayúscula) frente a los otros 9 en minúscula; `Safety` tenía además un `Database/Seeders/` residual con un `SafetyDatabaseSeeder` duplicado (stub muerto). CLA-527 renombró los 3 árboles a minúscula (`git mv`, ~96 renames, timestamps intactos → orden de migración idéntico), añadió los mapeos PSR-4 explícitos en `composer.json` (`Modules\FieldOps\Database\Factories\` etc. → rutas minúscula; namespaces `Database\` studly sin cambio), corrigió los 2 providers y 3 tests con rutas hardcodeadas, y borró el stub duplicado. Post-rename los 11 módulos son consistentes: el `auto-discover.migrations` (default nwidart, activo) encuentra a los 11 y el `loadMigrationsFrom` manual apunta a la misma ruta → el migrator deduplica, sin doble registro para nadie. Verificado: `migrate` completo 179 migraciones / 0 duplicados; autoload resuelve todas las clases `Database\*` desde las rutas minúscula. Consolidar a un único call site (quitar los 11 `loadMigrationsFrom` manuales o desactivar auto-discover) sigue siendo un refactor aparte, no hecho en CLA-527.

### Suite FieldOps amplia contaminada entre clases

La ejecución conjunta de toda la suite FieldOps mantiene dos fallos de harness preexistentes: varios `setUp()` usan `Role::create('super_admin')` y chocan con estado compartido (`RoleAlreadyExists`), y los tests de media pueden encontrar directorios de `storage/framework/testing/disks` creados con permisos incompatibles. En el hardening de CLA-267 la corrida amplia terminó con **209 passed / 649 assertions y 93 fallos** de esas dos familias; la regresión integrada aislada pasó **42/42 con 301 assertions** y los tests nuevos también pasan dentro de la corrida amplia. Pendiente normalizar roles con `firstOrCreate`/limpieza del PermissionRegistrar y los permisos del storage de testing en un ticket de infraestructura de pruebas; no mezclar ese refactor con tickets funcionales.

Además, no pasar varios paths de test a `sail artisan test` en este harness: pueden ejecutarse como procesos separados contra la misma base MySQL `testing` y competir durante `RefreshDatabase`. Usar un único proceso con `--filter='(ClaseA|ClaseB)'` o ejecutar cada archivo de forma serial.

### Infraestructura del portal cliente pendiente

El roadmap maestro fija `client.claesen-verlichting.be` como hostname objetivo y descarta el typo previo `clent.claesen-verlichting.be`. El dominio todavía no está provisionado: antes del despliegue se deben verificar DNS, TLS, reverse proxy, redirects OAuth, CORS, cookies y `SANCTUM_STATEFUL_DOMAINS`. Ver `docs/ai/fieldops-maintenance-roadmap.md`; el portal no debe considerarse desplegable solo porque el aislamiento backend esté listo.

**Nota CLA-344/345 (2026-08-07):** confirmado que la cookie de sesión de Sanctum pertenece al dominio del backend, no al de cada frontend — `config/cors.php` permite varios orígenes de frontend contra las mismas rutas `api/*`/`v1/*` con `supports_credentials: true`. Una sesión creada en Safety PWA/Sport es válida para llamadas hechas desde el Client Portal. CLA-344 cierra el login (nadie puede autenticarse *directamente* en el Client Portal sin rol `client`), pero no aísla la sesión entre apps a nivel de cookie — eso requeriría dominios de backend separados por app, fuera de alcance actual. Tenerlo en cuenta al verificar `SANCTUM_STATEFUL_DOMAINS`/`SESSION_DOMAIN` antes del despliegue de producción.

### Test roto: ClientPortalInfrastructureTest duplica código 'soccer' entre dos topologías

**Riesgo:** `test_client_portal_returns_only_the_members_authorised_topology_and_reduced_payload` (`Modules/FieldOps/tests/Feature/ClientPortalInfrastructureTest.php:31-54`) llama a su helper `topology()` dos veces en el mismo test (`$allowed` y `$hidden`, línea 33-34), y `topology()` (línea 89) crea un `TerrainType` con `code: 'soccer'` hardcodeado sin `firstOrCreate` — la segunda llamada choca contra el `unique` de `fo_terrain_types.code` y el test falla con `UniqueConstraintViolationException`. **Confirmado en aislamiento total** (ejecutando solo esta clase, sin ningún cambio de CLA-344/345 involucrado) — no es contaminación entre suites, es un bug propio del test.
**Impacto:** el único test que verifica el payload reducido/topología autorizada del endpoint específico del Client Portal está roto y no corre en CI tal como está.
**Pendiente:** cambiar el segundo `TerrainType::create(['code' => 'soccer', ...])` dentro de `topology()` para aceptar un código parametrizado (o usar `firstOrCreate`), y volver a correr para confirmar que las aserciones del payload siguen siendo correctas.

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

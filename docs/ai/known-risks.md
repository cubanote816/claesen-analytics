# Riesgos conocidos y deuda técnica — CAFCA Intelligence Hub

> Riesgos abiertos, bloqueantes, deuda técnica y decisiones pendientes.
> Última actualización: 2026-06-02 (DOCS-AI-001 / CLA-105)

---

## Bloqueantes activos

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

### Alpine.data() + alpine:init muerto en 4 location-pickers bajo wire:navigate

**Riesgo:** `complex-location-picker.blade.php`, `terrain-location-picker.blade.php`, `structure-location-picker.blade.php` y `electrical-board-location-picker.blade.php` registran su componente Alpine vía `@push('scripts')`/`@once` + `document.addEventListener('alpine:init', ...)`. Confirmado en `luminaire-frame-type-image-editor.blade.php` (CLA-278, commit `a935834`) que este patrón deja el componente completamente inerte (sin datos, sin listeners) cuando la página se alcanza navegando por click dentro del panel (`wire:navigate`, default de Filament) — solo funcionaba con una carga dura de la URL. Muy probable que estos 4 pickers tengan el mismo bug (no confirmado en vivo todavía).
**Estado:** Documentado, sin corregir — decisión explícita del usuario de acotar el fix de CLA-278 solo al editor de Frame Types. `luminaire-type-gallery-selector.blade.php` se auditó y confirmó que NO tiene este problema (usa `x-data="{...}"` inline, no depende de `alpine:init`).
**Fix de referencia:** migrar `@push('scripts')`/`@once` a `@script`/`@endscript` (Livewire) y registrar `Alpine.data(...)` directo, sin envolver en `addEventListener('alpine:init', ...)` — ver diff del commit `a935834` sobre `luminaire-frame-type-image-editor.blade.php`.
**Acción requerida:** ticket nuevo para auditar y corregir los 4 archivos.

---

## Deuda técnica

### Suite FieldOps amplia contaminada entre clases

La ejecución conjunta de toda la suite FieldOps mantiene dos fallos de harness preexistentes: varios `setUp()` usan `Role::create('super_admin')` y chocan con estado compartido (`RoleAlreadyExists`), y los tests de media pueden encontrar directorios de `storage/framework/testing/disks` creados con permisos incompatibles. En el hardening de CLA-267 la corrida amplia terminó con **209 passed / 649 assertions y 93 fallos** de esas dos familias; la regresión integrada aislada pasó **42/42 con 301 assertions** y los tests nuevos también pasan dentro de la corrida amplia. Pendiente normalizar roles con `firstOrCreate`/limpieza del PermissionRegistrar y los permisos del storage de testing en un ticket de infraestructura de pruebas; no mezclar ese refactor con tickets funcionales.

Además, no pasar varios paths de test a `sail artisan test` en este harness: pueden ejecutarse como procesos separados contra la misma base MySQL `testing` y competir durante `RefreshDatabase`. Usar un único proceso con `--filter='(ClaseA|ClaseB)'` o ejecutar cada archivo de forma serial.

### Infraestructura del portal cliente pendiente

El roadmap maestro fija `client.claesen-verlichting.be` como hostname objetivo y descarta el typo previo `clent.claesen-verlichting.be`. El dominio todavía no está provisionado: antes del despliegue se deben verificar DNS, TLS, reverse proxy, redirects OAuth, CORS, cookies y `SANCTUM_STATEFUL_DOMAINS`. Ver `docs/ai/fieldops-maintenance-roadmap.md`; el portal no debe considerarse desplegable solo porque el aislamiento backend esté listo.

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

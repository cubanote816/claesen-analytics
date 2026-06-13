# Handoff — CAFCA Intelligence Hub

> Estado global vivo del proyecto. Actualizar en cada cierre de ticket.
> Última actualización: 2026-06-13 (Sprint 2B UX **COMPLETADO** — BI-050→062 + UX-01/02/03/04/05/06/07/08 ✅ — listo para PR)

---

## Estado actual

- **Sprint activo:** BI — Sprint 2B UX (Monthly Billing Guardian mejoras UX)
- **Rama actual:** `feature/bi-sprint2b-billing-guardian`
- **Último ticket:** BI-2B-UX-06/07/08 ✅ — documentación UX (user-guide + data-sources + technical doc)
- **Próximo paso:** revisión visual en navegador → PR `feature/bi-sprint2b-billing-guardian` → `main`
- **Tests:** 95 passed / 200 assertions (módulo Intelligence)

### Sprint BI — Estado

| Sprint | Estado | Aprobación |
|--------|--------|------------|
| Sprint 0 — Integración BI→main | ✅ Done — PR #4 mergeado | ✅ Auditor GO |
| Sprint 1 — Mirrors + bi_config | ✅ Done — PR #5 mergeado a `main` (`558ec32`) | ✅ Auditor GO |
| Sprint 2 — Motor financiero | ⬜ Todo | ✅ (no requiere auditor gate) |
| Sprint 2B — Monthly Billing Guardian | ✅ **COMPLETADO** — BI-050→062 todos Done — pendiente PR | ✅ GO con **Auditor Gate en BI-052/053/054** |
| Sprint 3 — UI simulador | ⬜ Todo | ✅ (no requiere auditor gate) |
| Sprint 4 — Métricas | ⬜ Todo | ✅ (no requiere auditor gate) |

### Sprint 2B — Tickets

| Ticket | Título | Commit | Estado |
|--------|--------|--------|--------|
| BI-050 | Migración `intelligence_billing_alerts` + modelo | `5ba0ec7` | ✅ Done |
| BI-051 | `MonthlyBillingGuardianService` — estructura + §4.4.1 rerun policy | `4b262b7` | ✅ Done |
| BI-052 | Regla `missing_customer_invoice` — **Gate APPROVED** | `a3004b8`+`4490bcc` | ✅ Done |
| BI-053 | Reglas `overdue_receivable`+`partial_payment` — **Gate APPROVED** | `610dff7` | ✅ Done |
| BI-054 | Regla `unbilled_followup_cost` — costes followup no facturados — **Gate APPROVED** | `108f928` | ✅ Done |
| BI-055 | Reglas `billing_gap`+`credit_note`+`closed_with_balance` (sin gate) | `fdfaf66` | ✅ Done |
| BI-056 | Comando `intelligence:billing-guardian` — 4 opciones + output tabla | `f7803f3` | ✅ Done |
| BI-057 | Scheduler mensual (día 2, 07:00 Brussels, `--previous-month`) | `f7803f3` | ✅ Done |
| BI-058 | `MonthlyBillingControlPage` Filament V5 — KPIs + 5 tabs + Run Guardian | `d020ac2` | ✅ Done |
| BI-059 | Workflow open→review→confirmed|dismissed→resolved | `4b7ac2e` | ✅ Done |
| BI-060 | Reglas Guardian en `BiConfigPage` | ✅ ya en BI-019/052 | ✅ Done |
| BI-061 | Tests — suite completa 95 tests / 200 assertions | `37cdf8b` | ✅ Done |
| BI-062 | Documentación `docs/bi-monthly-billing-guardian.md` | `37cdf8b` | ✅ Done |
| BI-2B-UX-01/04/05 | Quick wins UX — status labels NL, Bedrag contextual, tab Maandafsluiting, banner link, recomendación expandible, KPI sublabels+tooltips, notificaciones orientativas, modal Guardian texto | `757e982` | ✅ Done |
| BI-2B-UX-03 | Columna Project con nombre + cliente + link ProjectInsights (no N+1, no broken links) | `0953245` | ✅ Done |
| BI-2B-UX-02 | Modal "Meer details" — contexto completo + evidence legible + aviso ámbar | `a7a0a61` | ✅ Done |
| BI-2B-UX-06/07/08 | Documentación UX — user-guide (9 pasos, Bevestigd≠Opgelost, Bedrag tabla, Projectinzichten) + data-sources (Wat betekent het Bedrag?) + technical doc (UX contextual, auditor gate) | `d7ab67a` | ✅ Done |

### BI-052 — Auditor Gate: APPROVED (2026-06-13)

**Regla:** `missing_customer_invoice` dispara cuando:
- hay actividad económica en el mes,
- activity_cost > €500 (`min_activity_amount`, comparador estricto `>`),
- no existe invoice no-CN en ese mes,
- el proyecto tiene contrato o estimate vinculado.

**Decisiones aprobadas por el auditor:**
- Comparador estricto: `>` threshold. Exactamente €500 NO dispara (fijado por tests).
- `CN%` no cuenta como factura válida.
- `amount_activity_cost` contiene costes detectados en mirror_costs.
- `amount_estimated` solo se rellena con `contract_price` confiable; sin contrato → NULL.
- Horas/workdocs solos no disparan por ahora.
- Config renombrada: `min_activity_amount` para esta regla; `min_cost_amount` reservado para `unbilled_followup_cost` (BI-054).

**Evidencia del gate (datos reales mayo 2026, dry-run):**
- Caso A: P20250063 Limburg Diepenbeek — €20.642,84, 120 días sin factura
- Caso B: P20250054 Gemeente Heuvelland — €9.925,18 + 193,34h, 120 días
- Caso C (edge): P20260026 De Raedt Ivan — €2.110,43, 31 días
- Caso N: P20260024 Balteau — €9.016,05 PERO facturado en mayo → excluido ✓
- Caso L: sin fila real en €500,00 exacto — comportamiento fijado por tests (500,00 no dispara / 500,01 dispara)
- **Hallazgo demo:** P20260029 vs P20260030 (ambos Derriks, €5.600) — uno facturado, otro no → alerta correcta. Caso ideal para demo interna del módulo.

### BI-053 — Auditor Gate: APPROVED (2026-06-13)

**Regla `overdue_receivable`:** dispara cuando `fl_paid=false`, no es CN%, `date_expiration < hoy`, y saldo abierto `(total_price − total_paid) > min_amount` (€500, estricto `>`). Severity: >60 días vencida → critical, si no → high (frontera 60/61 fijada por tests).

**Regla `partial_payment`:** dispara cuando `fl_paid=false`, `total_paid > 0`, saldo > min_amount, y **aún no vencida** (o sin fecha). Severity: medium.

**Decisiones aprobadas:**
- Exclusión mutua por `date_expiration`: al vencer, la parcial pasa a overdue — nunca doble alerta.
- Umbral compartido `min_amount` (€500) para ambas reglas.
- Semántica snapshot: saldo que sigue abierto re-alerta el periodo siguiente (dedup_key incluye periodo) — intencional.
- `fl_paid=true` excluye siempre (el bit manda sobre el cálculo).
- Schema: `total_price`/`total_paid` añadidos al mirror; sync ampliado a "6 meses O fl_paid=0" (mirror: 113 → 130 facturas; la impagada más vieja es de 2009).

**Evidencia del gate (datos reales, dry-run — 32 overdue: 20 critical / 12 high):**
- Caso A: F25260007 TC Tenkie — €65.867,48, 286 días, critical
- Caso B: F25260201 Happy Waregem — €33.903,52, 12 días, high (severity distinta)
- Caso C (edge): F21220158 K.F.C. St-Job — €550,55, justo sobre umbral
- Caso N: F24250178 — €420,93 ≤ €500 → excluido ✓
- Caso L: sin fila real en €500,00 — fijado por tests (500,00 no / 500,01 sí)
- Partial real hoy: 0 (todas las parciales ya vencieron → overdue, exclusión mutua correcta)

### BI-054 — Auditor Gate: APPROVED (2026-06-13)

**Regla:** `unbilled_followup_cost` dispara cuando el total de costes con `invoiced=false` en el período, agrupado por proyecto, supera `min_cost_amount` (€500, estricto `>`).

**Decisiones aprobadas:**
- **Evaluación a nivel proyecto** (no por ítem individual): `SUM(cost_price × quantity) > min_cost_amount`. Aprobado explícitamente porque detecta acumulación de costes pequeños no facturados que suman riesgo operativo real.
- Comparador estricto `>`: exactamente €500 NO dispara.
- Campo fuente: `intelligence_mirror_costs.invoiced = false` → mapea a `followup_cost.already_invoiced` del ERP.
- Solo suma costes `uninvoiced`; los `invoiced=true` del mismo proyecto no entran.
- Threshold configurable: `billing_guardian_rules.min_cost_amount` (reservado para esta regla, separado de `min_activity_amount` de BI-052).
- Severity tiers: `medium ≤ €10k`, `high > €10k`. No `critical` por ahora — observar datos reales antes de añadir tier adicional.
- evidence_json: `{ count_items, total_amount, cost_types[] }`.
- recommendation: holandés, texto claro con ref proyecto + instrucción CAFCA.

**Desviación aprobada del spec original:**
> Auditor approved project-level aggregation instead of per-item threshold because multiple small uninvoiced costs on the same project represent a real billing risk.

**Tests:** 15 pasados / 26 assertions (BillingGuardianUnbilledCostTest.php). Commit `108f928`.

### Sprint 1 — Tickets (todos ✅)

| Ticket | Título | Commit | Estado |
|--------|--------|--------|--------|
| BI-010 | `contract_price`, `type`, `state` → `intelligence_mirror_projects` | `5002265` | ✅ Done |
| BI-011 | `invoiced` (boolean) → `intelligence_mirror_costs` + sync (`already_invoiced`) | `f8383fd` | ✅ Done |
| BI-012 | `relation_id`, `date_expiration`, `fl_paid` → `intelligence_mirror_invoices` + sync | `7984209` | ✅ Done |
| BI-013 | `intelligence_mirror_estimate_calc` — factores MAMO (6.677 filas 1:1) | `358cbe5` | ✅ Done |
| BI-014 | `intelligence_mirror_project_links` (1.658 filas) + fix composite-key save | `ec89fcc`+`a0b8604` | ✅ Done |
| BI-015 | `intelligence_mirror_project_results` — 45 filas validadas, profit_percent decimal(10,4) | `eb1ae6a` | ✅ Done |
| BI-016 | `intelligence_mirror_workdocs` — 1.782 filas validadas | `e86255a` | ✅ Done |
| BI-017 | `intelligence_bi_config` + seeder 5 entradas (firstOrCreate) | `a118d92` | ✅ Done |
| BI-018 | `BiConfigService` — get/set/dot-notation/cache 1h + invalidación | `04c35b2` | ✅ Done |
| BI-019 | `BiConfigPage` Filament V5 — 5 secciones, super_admin only | `3280d83` | ✅ Done |
| BI-020 | Labor sync window — respeta `labor_sync_schedule`, ventanas que cruzan medianoche | `9740181` | ✅ Done |
| BI-021 | Tests Intelligence — 27 tests / 61 assertions (3 archivos Feature) | `b2b6d8f` | ✅ Done |
| BI-022 | Fix N+1 `syncProjects` — batch whereIn por chunk; colgado → 1.14s | `c46db98` | ✅ Done |

### Hallazgos clave Sprint 1 (para el auditor)

- **BI-011:** campo correcto es `followup_cost.already_invoiced` (12.735 true / 190 false). `invoice` bit es flag de tipo, NO estado de facturación. `fl_booked_to_invoice` tiene 1 sola fila.
- **BI-015:** `profit_percent` requiere `decimal(10,4)` — caso real P20180031 NMBS: 11.852% (cost €920, invoiced €110.005). `rpt_project_results.invoiced` es importe float €, no boolean.
- **BI-016:** `workdoc.fl_invoice=1` en 75% de filas → flag de tipo facturable. `fl_paid=1` en solo 1 fila — no es señal fiable aún. `fl_needinvoiced` descartado (9 filas).
- **BI-014 fix:** `updateOrCreate` con PK compuesta generaba `UPDATE WHERE id IS NULL` — bug latente que habría fallado en el primer re-sync de producción. Detectado por los tests de BI-021.
- **Mirrors poblados:** projects 127 (contract_price/type/state OK, zipcode 126/127), project_results 45/45, workdocs 1.782/1.782, relations 3.259, estimate_items 144.051.

**Documento maestro:** `docs/bi-sprint-plan.md`
**Rama Sprint 1:** `feature/bi-sprint1-data` | Sprint 2B → desde `main` tras merge de Sprint 1

### Estado de ramas feature

| Rama | Estado vs `main` |
|------|-----------------|
| `feature/mailing` | ✅ Ya en main |
| `Safety_Inspections` | ✅ Ya en main |
| `feature/static-site-publish` | ✅ Mergeada `ff11888` |
| `feature/website-work-details` | ✅ Mergeada `1169646` |

### Work Details / In Action — tickets mergeados (2026-06-05)

| WEB | CLA | Título | Commit | Estado |
|-----|-----|--------|--------|--------|
| WEB-012 | CLA-133 | `work_story`, `challenge`, `solution`, `result` + `detail_gallery` collection | `7f7f4f9` | ✅ Done |
| WEB-013 | CLA-134 | Translations NL + EN — Work Details section | `020a5f3` | ✅ Done |
| WEB-014 | CLA-135 | Filament — Work Details / In Action section | `b4c4ab4` | ✅ Done |
| WEB-015 | CLA-136 | API Resource — expose Work Details + `detail_gallery` | `a1aa7e4` | ✅ Done |
| WEB-016 | CLA-137 | Feature tests — Work Details / In Action | `76360c4` | ✅ Done |
| Fix | — | Comentario erróneo locale `de` — Gemini traduce nl/en/fr/**de** | `2d6c882` | ✅ Done |

Merge commit: `1169646` — resolución de conflictos en `ProjectResource.php` y `Project.php`:
- `work_story/challenge/solution/result` usan `resolveLocaleValue()` (consistente con WEB-008)
- `detail_gallery` caption/alt también usan `resolveLocaleValue()` (feature branch dejaba valores raw)
- `getAiTranslatableAttributes()` incluye `client` (HEAD) + los 4 campos Work Details

### Sprint Static Site Auto-Publish — tickets mergeados (2026-06-05)

| WEB | CLA | Título | Commit | Estado |
|-----|-----|--------|--------|--------|
| WEB-017 | CLA-138 | `config/static_site.php` — foundation config | `4285b72` | ✅ Done |
| WEB-018 | CLA-139 | `PublicationState` — migration, model, enum | `fbfdafc` | ✅ Done |
| WEB-019 | CLA-140 | `StaticSitePublicationService` + `WebhookResult` + job stub | `5458047` | ✅ Done |
| WEB-020 | CLA-141 | `TriggerStaticSiteRebuildJob` — debounce + retry | `0c7c51c` | ✅ Done |
| WEB-021 | CLA-142 | Wire observers → `StaticSitePublicationService` | `fb5bb05` | ✅ Done |
| WEB-022+023 | CLA-143+144 | Filament publication widget + botón manual + traducciones | `9cf47f9` | ✅ Done |
| WEB-024 | CLA-145 | Node.js webhook receiver (`scripts/astro-rebuild/`) | `2c34a3f` | ✅ Done |
| WEB-025 | CLA-146 | Feature tests — static site publication (Laravel + Node) | `057f1bf` | ✅ Done |
| Fix | — | GalleryMetadataJobTest: aserción + enable flag corregidos | `2e8732d` | ✅ Done |

Merge commit: `ff11888` (PR #3)

### Arquitectura del pipeline

```
Admin guarda proyecto / media
  → ProjectObserver / MediaObserver
  → StaticSitePublicationService::requestRebuild()
      → PublicationState::markPending()          (MySQL)
      → TriggerStaticSiteRebuildJob::dispatch()  (debounce + dispatch_key)
          → StaticSitePublicationService::sendWebhook()
              → POST /rebuild  (HMAC-SHA256, anti-replay 300s)
              → 202 = solicitud aceptada; build corre async en frontend
              → GET /health    = estado real del build

Frontend: Node.js webhook-receiver.mjs en 192.168.60.20
  → responde 202 inmediatamente
  → npm run build -- --outDir releases/<YYYYMMDDTHHmmss>/
  → rename(2) → swap atómico del symlink current
```

### API pública Website — URLs operativas

| Método | URL | Descripción |
|--------|-----|-------------|
| `GET` | `/v1/website/projects` | Listado paginado (`?category`, `?year`, `?featured`, `?per_page`) |
| `GET` | `/v1/website/projects/{slug}` | Detalle completo — incluye `work_story/challenge/solution/result/detail_gallery` |
| `GET` | `/v1/website/projects/categories` | Enum de categorías |
| `GET` | `/v1/website/projects/years` | Años con proyectos publicados |

Locale resuelto por `Accept-Language` vía `SetPanelLocale` middleware (nl/en/fr/de).

### Riesgos pendientes antes de producción

1. `STATIC_SITE_REBUILD_ENABLED=false` por defecto — activar explícitamente en .env de producción
2. ~~Ghost migration `add_work_details_to_website_projects_table`~~ — resuelto: `feature/website-work-details` mergeado en `main`
3. Permisos de escritura de `astro-deploy` sobre `WEBHOOK_RELEASES_DIR` y `WEBHOOK_PROJECT_DIR`
4. Configurar `tries`/`backoff` de `TriggerStaticSiteRebuildJob` antes de activar con Redis en producción
5. Proyectos publicados en producción sin `work_story/challenge/solution/result` rellenos — la API devuelve `null`; requiere que editores rellenen en Filament o se lance auto-traducción Gemini

### Tests ejecutados en verificación previa al PR

- Laravel: 51/51 ✅ (módulo Website completo)
- Node.js: 16/16 ✅ (HMAC, health, deploy, failed build, concurrent builds, pruning)
- Secret scan: limpio

## Reglas de arranque persistentes

Las reglas de arranque de Antigravity viven en:

- `AGENTS.md` — reglas del repositorio (leído automáticamente por agentes compatibles)
- `.agents/rules/00-project-startup.md` — protocolo completo de arranque

Todo agente debe leer estos archivos antes de cualquier acción.

---

## Módulos activos

| Módulo | Estado | Rama | Documento específico |
|--------|--------|------|---------------------|
| **Mailing** | ✅ Fase 0+1+2 completadas / Fase 3 en Backlog | `feature/mailing` | `docs/Mailing/mailing-platform-master.md` |
| **Website** | ✅ WEB-001→025 mergeados en `main` (incl. Work Details + Static Site) | `main` | `docs/website-sprint-handoff.md` |
| **Safety** | ✅ Sprint completado (SAF-001 a SAF-016) | `Safety_Inspections` | `docs/safety-sprint-linear-tickets.md` |
| **Performance** | 🚧 ~85% | `main` | Ver `CLAUDE.md` |
| **Intelligence / BI** | ✅ Sprint 1 completo (BI-010→022) — pendiente GO + merge | `feature/bi-sprint1-data` | `docs/bi-sprint-plan.md` |
| **Prospects** | 🚧 ~80% (PROS-BUG-001+002 cerrados, FAB mailing operativo, sync dashboard exception feed) | `main` | Ver `CLAUDE.md` |
| **Cafca** | ✅ ~90% | `main` | Ver `CLAUDE.md` |
| **Core** | ✅ ~95% | `main` | Ver `CLAUDE.md` |

---

## Cambios recientes — UX / Bugs (2026-06-07)

| Ticket | Linear | Título | Commits | Estado |
|--------|--------|--------|---------|--------|
| MAI-UX-002 | CLA-143 | Improve campaign content snapshot preview | `fac901f` | ✅ Done |
| MAI-TEST-001 | CLA-144 | Fix 68 failing Mailing feature tests + 2 production bugs | `2bdd181` | ✅ Done |
| MAI-UX-003 | CLA-145 | Campaign view: accordion → modal "View full content" | — | ✅ Done |
| PROS-UX-001 | CLA-146 | Prospects: contextual mailing FAB para selección | `285a8f3`, `c5c65b9` | ✅ Done |
| PROS-BUG-002 | CLA-147 | Fix FAB sync + posición fixed (scope, page-select, interval) | `3d8a7b9`→`ca581e1` | ✅ Done |
| PROS-UX-003 | CLA-148 | Sync Dashboard: Aandacht vereist exception feed | `a95a42f` | ✅ Done |
| PROS-UX-002 | — | Compact mailing FAB on mobile (circular icon+badge on ≤640px) | `8a9cc51` | ✅ Done |


---

## Backlog Mailing — completado

| Ticket | Linear | Título | Commit | Estado |
|--------|--------|--------|--------|--------|
| MAI-BUG-001 | CLA-133 | Approve visible en campañas terminales | `c837782` | ✅ Done |
| MAI-BUG-002 | CLA-134 | Contadores dobles + FAILED + OAuth null cacheado | `6189e47` | ✅ Done |
| MAI-BUG-003 | CLA-135 | Submit visible en campañas terminales | `50c3a93` | ✅ Done |
| MAI-BUG-004 | CLA-136 | Ruta unsubscribe incorrecta bloqueaba envío | `fe0638a` | ✅ Done |
| MAI-BUG-005 | CLA-137 | Cancel visible en estados terminales (listado) | `10c6324` | ✅ Done |
| MAI-BUG-006 | CLA-139 | Cancel/Approve/Submit en detalle campaña | `db8605d` | ✅ Done |
| MAI-UX-001 | CLA-138 | Campaign engagement detail view | `51fa208` | ✅ Done |

## Hoja de ruta — prioridades

| Prioridad | Ticket | Linear | Título | Estado |
|-----------|--------|--------|--------|--------|
| **1** | BI-000 | — | Sprint BI — Sprint 0: integración + PR #4 | ✅ Done |
| **2** | BI-010→022 | — | Sprint BI — Sprint 1: mirrors + bi_config | ✅ Done — pendiente GO + merge |
| **3** | BI-050→062 | — | Sprint BI — Sprint 2B: Monthly Billing Guardian | ⬜ Desbloqueado tras merge |
| 4 | OPS-MAI-001 | CLA-140 | Mailing production readiness validation | ⬜ Todo |
| 5 | — | — | Website backfill media (`website:regenerate-media`) + validar deploy frontend | Operativo |
| 6 | — | — | Prospects CRM — calidad de datos, filtros, segmentos | 🚧 ~78% |
| 7 | — | — | Performance / Watchdog — impacto financiero si gerencia lo prioriza | 🚧 ~85% |
| Bloqueado | Mailing Fase 3 | MAI-031→036 | Scoring, predicciones, IA | ⏸ Hasta 4–6 sem datos reales |

---

## Flujo de deploy a producción

### Resumen

```
git push origin main
       ↓
GitHub Actions — "Build Laravel release" (composer, npm build, tar.gz)
       ↓  artefacto listo en GitHub Releases como 'production-latest'
ssh bert@192.168.60.10
bash /var/www/backend.claesen-verlichting.be/deploy.sh
```

El deploy al servidor es **manual** — el CI construye el artefacto pero no despliega automáticamente. Esto da control sobre cuándo entra cada cambio a producción.

### Lo que hace cada parte

**GitHub Actions (`.github/workflows/deploy.yml`)**
- Instala dependencias PHP (composer --no-dev) y Node (npm ci)
- Compila assets frontend (npm run build)
- Empaqueta el release en `release.tar.gz` (excluye `.git`, `node_modules`, `tests`, `storage/app/*`, etc.)
- Sube a GitHub Releases tag `production-latest` tres assets: `release.tar.gz`, `release.tar.gz.sha256`, `release.env`

**deploy.sh (`/var/www/backend.claesen-verlichting.be/deploy.sh`)**
1. `cd $APP_DIR` — garantiza que todos los comandos operen en el directorio correcto
2. `php artisan down` — modo mantenimiento (`|| true` si ya estaba activo)
3. `mysqldump` — backup BD antes del deploy
4. `gh release download` — descarga `release.tar.gz` + `release.tar.gz.sha256`
5. `sha256sum -c` — verifica integridad del tarball
6. `rsync --delete` — aplica el release preservando `.env` y `storage/`
7. `php artisan migrate --force`
8. `php artisan optimize:clear` + `filament:upgrade --no-interaction` + `php artisan optimize`
9. `php artisan queue:restart`
10. `php artisan up` — sale de mantenimiento

### Configuración CI relevante

| Variable | Valor |
|----------|-------|
| PHP_VERSION | 8.4 (alineado con producción y composer.lock) |
| NODE_VERSION | 22 |
| RELEASE_TAG | `production-latest` (mutable, siempre apunta al último build) |
| CACHE_STORE (build) | `array` (override en CI; producción usa `database`) |
| SESSION_DRIVER (build) | `array` (override en CI; producción usa `database`) |

### Notas operativas

- El workflow requiere `permissions: contents: write` para crear/actualizar la GitHub Release.
- El servidor usa `gh` CLI autenticado como `cubanote816` (`/home/bert/.config/gh/hosts.yml`).
- El backup de BD se guarda en `storage/app/backups/db_pre_deploy_YYYY-MM-DD_HH-MM.sql`.
- Si el deploy falla después de `artisan down`, ejecutar manualmente `php artisan up` desde `APP_DIR`.
- Para automatizar el deploy (sin SSH manual) habría que añadir un paso SSH al workflow con la clave privada como GitHub Secret — decisión pendiente.

---

## Bloqueantes actuales

- **MAI-026** — Webhook handler ESP externo: bloqueado por decisión de gerencia. No tocar.
- **Mailing Fase 3** (MAI-031 a MAI-036) — bloqueada hasta 4–6 semanas de datos reales en producción.
- **Backfill Website media** — `php artisan website:regenerate-media` pendiente de ejecutar en producción.

Ver `docs/ai/known-risks.md` para el detalle completo.

---

## Próximos pasos recomendados

1. **Sprint BI — Sprint 0** (ahora, rama `feature/bi-foundation`):
   ```
   git checkout -b feature/bi-foundation
   git cherry-pick 8d563e8 a8eedcf 5796a32
   # verificar no-colisión de las 6 migraciones con main
   php artisan test --testsuite=Modules --filter=Intelligence
   ```
2. **Deploy Website en producción:**
   - `php artisan migrate` (columnas `work_story/challenge/solution/result` + tabla `publication_states`)
   - Instalar receiver Node.js en 192.168.60.20 (`scripts/astro-rebuild/README.md`)
   - Configurar `.env`: `STATIC_SITE_REBUILD_ENABLED=true`, `STATIC_SITE_WEBHOOK_SECRET`, `STATIC_SITE_WEBHOOK_URL`, `STATIC_SITE_HEALTH_URL`
   - Firewall: puerto 9000 solo desde 192.168.60.10
2. **Website backfill media:** ejecutar `php artisan website:regenerate-media` en producción (pendiente desde WEB-007).
3. **Rellenar Work Details en Filament:** `work_story/challenge/solution/result` vacíos en proyectos publicados — editores o trigger Gemini manual.
4. **Mailing Fase 3:** esperar datos reales de campañas en producción antes de iniciar MAI-031.
5. **Performance:** continuar mejoras de insights y Watchdog según prioridad.
6. **Prospects:** completar CRM y campañas email (~75%).

---

## Cambios recientes

| Fecha | Ticket | Acción |
|-------|--------|--------|
| 2026-06-13 | BI-011→022 | Sprint 1 completado en una sesión: 12 tickets + 1 fix colateral. Mirrors nuevos (estimate_calc, project_links, project_results, workdocs), bi_config + service + página Filament, ventana labor sync, 27 tests, fix N+1. Todos los commits en `feature/bi-sprint1-data`. |
| 2026-06-13 | BI-010 | `contract_price`, `type`, `state` añadidos a `intelligence_mirror_projects`. Migración `2026_06_13_100000` aplicada. Sync completo pendiente (SQL Server no alcanzable desde Docker al momento del commit). Commit `5002265` en `feature/bi-sprint1-data`. |
| 2026-06-13 | BI-000 | PR #4 mergeado — `feature/bi-foundation` → `main`. Cherry-pick `8d563e8`+`a8eedcf` aplicados. 6 migraciones `2026_05_27_*` en main. Sail validado (migrate, sync --relations 3.259, sync --estimates 144.051). |
| 2026-06-13 | BI-PLAN | Done — Plan Sprint BI completado y aprobado por auditor. Sprint 0+1+2B GO. Auditor Gate formalizado en BI-052/053/054 con 5-ejemplo obligatorio. Documento: `docs/bi-sprint-plan.md`. |
| 2026-06-12 | OPS | Done — Fix GitHub Actions deploy workflow (5 bugs: actions versions @v4, PHP 8.3→8.4, .env.example `\nMAILING_DRIVER`, sqlite touch, CACHE/SESSION array, rsync self-copy). Fix deploy.sh (cd APP_DIR, artisan down \|\| true, sha256 verify, filament --no-interaction, php artisan optimize). Release `production-latest` operativa. |
| 2026-06-09 | Mailing | Done — One-time unsubscribe links (renders success immediately if already unsubscribed) and Livewire real-time auto-polling (5s) for campaign list, recipients table, and metrics widget. Verified with passing tests. |
| 2026-06-09 | Mailing | Done — Log and display 'Unsubscribed' status (Uitgeschreven) for unsubscribed or suppressed (unsubscribed) recipients instead of displaying 'Skipped (No email)'. Verified with tests passing in Sail. |
| 2026-06-09 | CORE-BUG-003 / CLA-153 | Done — Fix ProjectInsight namespace import in ProjectInsightSeeder and push all local commits to remote origin main. |
| 2026-06-09 | CORE-BUG-002 / CLA-152 | Done — Optimize login layout (reduce margins) and display the attempted Microsoft email address in the access denied error at the top of the login form using AUTH_LOGIN_FORM_BEFORE hook. |
| 2026-06-09 | PROS-UX-003 / CLA-148 | Done — Replace Sync Dashboard recent activity feed with exception-based Aandacht vereist section, retry action, and healthy empty state. Verified with Sail Prospects tests passing. |
| 2026-06-09 | CORE-BUG-001 / CLA-151 | Done — Render Microsoft login errors in custom login view. Verified locally. |
| 2026-06-09 | WEB-BUG-002 / CLA-150 | Done — Make website projects JSON migration idempotent by wrapping table and column alter statements in schema checks. Verified with tests passing. |
| 2026-06-09 | WEB-BUG-001 / CLA-149 | Done — Remove CAST(AS JSON) from website_projects migration to avoid syntax errors on MariaDB. Verified with tests passing. |
| 2026-06-06 | MAI-TEST-001 / CLA-144 | Done — Fix 68 failing Mailing tests: EmailTemplateFactory (new), ProspectFactory (new + afterCreating), CampaignMessageFactory fixes, EmailTemplate/MessageEvent/Prospect model fixes, CheckDeliverabilityAlertsCommand production bugs (`[$alert,$created]`→`wasRecentlyCreated`, resilient role query), SelectAbWinnerCommand GROUP BY, DeliverabilityAlertTest/SchemaFoundationTest fixes — `2bdd181` |
| 2026-06-06 | MAI-UX-002 / CLA-143 | Done — Campaign content snapshot preview: subject + plain-text preview visible sin accordion; Full Content expandible — `fac901f` |
| 2026-06-06 | MAI-BUG-007 / CLA-142 | Done — ONLY_FULL_GROUP_BY crash en CampaignMetricsWidget (chronological global scope) — `742c4f6` |
| 2026-06-06 | MAI-CONTENT-001 / CLA-141 | Done — Seeder LED 2027 templates (3 plantillas comerciales NL) — `0f79447` |
| 2026-06-06 | MAI-BUG-005 / CLA-137 | Done — Cancel action hidden on terminal campaigns (canTransitionTo guard) — `10c6324` |
| 2026-06-06 | MAI-BUG-004 / CLA-136 | Done — One-click unsubscribe route incorrecto (`mailing.unsubscribe.oneclick` → `api.mailing.unsubscribe.oneclick`) — `fe0638a`. Confirmado: Sent 2 / Failed 0 |
| 2026-06-06 | MAI-BUG-003 | Submit button visible on non-draft campaigns for super_admin — `50c3a93` |
| 2026-06-06 | MAI-BUG-002 | Campaign send accounting fixed — double count, completed-when-all-fail, null token cached — `6189e47` |
| 2026-06-06 | MAI-BUG-001 | Approve button visible on terminal campaigns for super_admin — `c837782` |
| 2026-06-06 | PROS-BUG-001 / CLA-133 | Bug FAB mailing cerrado — 3 causas raíz: `$selectedTableRecords` no limpiado en PHP al cambiar tab; FAB saltaba Alpine `mountAction()` (PHP siempre recibía `[]`); `livewire:update` no existe en Livewire 3 — commits `85a9100` `69246d6` `e5c22d9` |
| 2026-06-05 | verificación | API pública `/v1/website/projects/{slug}` confirma `work_story/challenge/solution/result/detail_gallery` operativos |
| 2026-06-05 | WEB-012→016 | Merge `feature/website-work-details` → `main` — Work Details / In Action — `1169646` |
| 2026-06-05 | Fix | Comentario erróneo locale `de` corregido — `2d6c882` |
| 2026-06-05 | WEB-017→025 | Merge `feature/static-site-publish` → `main` (PR #3) — Static site pipeline — `ff11888` |
| 2026-06-03 | TEST-GATE-001 | Arnés obligatorio de testing — commits `0278d05` `92199c3` |
| 2026-06-03 | WEB-011 / CLA-111 | Seguimiento Consultation Requests — commits `2b500b1` `569c2c0` |
| 2026-06-02 | WEB-010 / CLA-110 | Email transaccional Consultation Requests — commit `0588594` |
| 2026-06-02 | WEB-009 / CLA-109 | IA caption/alt galería portfolio — commits `f3d57c8` `112aef8` `5c1c972` |
| 2026-06-02 | WEB-008 / CLA-108 | Base multidioma portfolio nl/en/fr/de — commits `28e19aa` `80865c8` `9a626cd` |
| 2026-06-02 | DOCS-AI-003 / CLA-107 | Verificación arranque persistente Antigravity — commit `0ad1529` |
| 2026-06-02 | DOCS-AI-002 / CLA-106 | Creado `AGENTS.md` + `.agents/rules/00-project-startup.md` — reglas de arranque persistentes |
| 2026-06-02 | DOCS-AI-001 / CLA-105 | Creado sistema AI harnesses en `docs/ai/` + `handoff.md` raíz |
| 2026-05-30 | MAI-030 / CLA-105 | Cerrada Fase 2 Mailing — documentación y preparación PR |
| 2026-05-30 | MAI-027 / CLA-3b20265 | Alertas de entregabilidad — hard bounce + spam complaint |
| 2026-05-30 | MAI-023 / CLA-5699c75 | Follow-up automático por comportamiento |
| 2026-05-30 | MAI-022 / CLA-79270f7 | A/B testing de asunto — split + winner automático por CTR |
| 2026-05-30 | MAI-025 / CLA-7b00685 | Página de preferencias de categoría |

---

## Verificación de arranque persistente Antigravity

- Fecha: 2026-06-02
- Ticket: DOCS-AI-003 / CLA-107
- Resultado: OK

Se verificó que una nueva sesión de Antigravity lee y aplica correctamente:

1. `CLAUDE.md`
2. `handoff.md`
3. `docs/ai/README.md`
4. `AGENTS.md`
5. `.agents/rules/00-project-startup.md`

Reglas confirmadas activas:

- Sin ticket Linear activo → sin edición de archivos.
- Sin plan aprobado → sin implementación.
- Sin GO técnico → no se marca Done.
- No se leen ni copian secretos.
- Mailing Fase 3 bloqueada hasta 4–6 semanas de datos reales en producción.
- MAI-026 bloqueado hasta decisión de gerencia.
- SQL Server legacy / Cafca sigue siendo ReadOnly.

Próximo paso: definir el próximo ticket Linear antes de iniciar cualquier trabajo nuevo.

---

## Cómo reanudar una sesión

```
Lee CLAUDE.md, handoff.md y docs/ai/README.md.
Luego lee el documento específico del módulo activo.

Para Mailing: docs/Mailing/mailing-platform-master.md
Para Website: docs/website-sprint-handoff.md
Para Safety:  docs/safety-sprint-linear-tickets.md
```

Ver `docs/ai/prompt-templates.md` para prompts de reanudación listos para copiar.

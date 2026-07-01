# Handoff — CAFCA Intelligence Hub

> Estado global vivo del proyecto. Actualizar en cada cierre de ticket.
> Última actualización: 2026-07-01 (Employee module: filas navegables en Hours Dashboard y Month Stats; fix Target prorrateado en semanas de borde de mes)

---

## Estado actual

- **Sprint activo:** FieldOps (rama: `main`)
- **Rama actual:** `main`
- **Último hito código:** `1872576` (2026-07-01) — EMP-013 / CLA-188: Month Stats — fix Target no prorrateado en semanas de borde de mes + fila navegable.
- **Hito previo:** `45cf1c7` (2026-07-01) — EMP-012 / CLA-187: Hours Dashboard — fila completa del listado de empleados navegable.
- **Hito previo:** `fc06a8b` (2026-07-01) — EMP-011 / CLA-186: EmployeeHoursSummaryWidget con selector de mes + estado vacío sin horas.
- **Hito previo:** `069792d` (2026-07-01) — EMP-008/009/010 / CLA-183/184/185: Hours Dashboard sin límite top-10, fix gráfica Monthly Hours Trend, filtro unificado de temporalidad.
- **Último hito infra:** `667416a` (2026-06-27) — CORS corregido en nginx producción, deploy script endurecido, todos los scripts de servidor versionados en `infrastructure/`. Release activa: `20260627170653`.
- **Próximo paso:** sin ticket activo, definir con auditor.

### Sesión 2026-07-01 — Employee module: Hours Dashboard (listado, gráfica, filtro), widget dashboard ✅ Done

**Commits:**

| Hash | Tickets | Descripción |
|------|---------|-------------|
| `069792d` | EMP-008/009/010 · CLA-183/184/185 | Hours Dashboard: listado completo de empleados, fix gráfica, filtro unificado de temporalidad |
| `fc06a8b` | EMP-011 · CLA-186 | EmployeeHoursSummaryWidget: selector de mes + estado vacío |
| `45cf1c7` | EMP-012 · CLA-187 | Hours Dashboard: fila completa del listado de empleados navegable |
| `1872576` | EMP-013 · CLA-188 | Month Stats: fix Target no prorrateado en semanas de borde de mes + fila navegable |

**EMP-008 (CLA-183) — Listado completo, sin límite top-10:**
- `EmployeeDashboardRankingService::getTopEmployees()` — quitado `->take(10)`; ahora devuelve todos los empleados activos (`tracks_hours=true`), ordenados por horas desc.
- Afecta también al endpoint público `GET /api/v1/employees/rankings` (mismo servicio compartido) — decisión aprobada explícitamente.
- Sección renombrada "Top Employee Rankings" → "Employees".

**EMP-009 (CLA-184) — Fix gráfica Monthly Hours Trend (aparecía en blanco):**
- `window.Chart` nunca se cargaba en esta página → Chart.js se inyecta dinámicamente vía CDN.
- Bug real de Carbon: `Carbon::now()->endOfMonth()` (día 31) + `subMonth()` repetido produce overflow (31-jul − 1 mes → 1-jul, no 30-jun), duplicando un mes y perdiendo otro. Fix: iterar con base `startOfMonth()`.
- Primer pintado dependía de `requestAnimationFrame` no determinista → `animation: false`.

**EMP-010 (CLA-185) — Filtro unificado de temporalidad:**
- `EmployeeHoursDashboard.php` — un solo estado de periodo (`periodPreset`, `periodYear`, `customStartDate`, `customEndDate`, todos `#[Url]`) + `applyFilter()` recalcula gráfica y tabla juntas.
- Presets: Q1, Q2, Q3, Q4, H1 (Jan-Jun), H2 (Jul-Dec), año completo, rango personalizado.
- `EmployeeDashboardRankingService::getDashboardData()` — firma `?string $year` → `?string $startDate, ?string $endDate`; `getMonthlyHoursTrend()` genera buckets dentro del rango real (no fijo a 12 meses).
- **Bug preexistente corregido:** `getDashboardData($year)` ignoraba `$year`, siempre usaba los últimos 12 meses desde hoy. `total_working_days` también estaba fijo al año calendario completo.
- `EmployeeDashboardController` mantiene compatibilidad con `?year=` además de aceptar `?start_date=&end_date=`.

**EMP-011 (CLA-186) — EmployeeHoursSummaryWidget (dashboard admin):**
- Selector de mes (`type=month`, solo granularidad mensual) en vez de mes fijo.
- Nuevo flag `hasHoursLogged`; cuando no hay horas registradas ese mes, ya no muestra "Top 3" con empleados a 0h — mensaje explícito en su lugar.

**EMP-012 (CLA-187) — Hours Dashboard: fila completa navegable:**
- La tabla de empleados solo tenía el link en el nombre; ahora toda la fila navega a `EmployeeMonthStats` (click en cualquier celda), vía `Livewire.navigate()` + Alpine, con guard para no duplicar navegación si el click cae sobre el `<a>` del nombre.

**EMP-013 (CLA-188) — Month Stats: fix Target prorrateado + fila navegable:**
- **Bug encontrado durante investigación** (no reportado inicialmente): `EmployeeTimeService::getMonthWeeksStats()` mostraba el Target semanal completo (ej. 40h) incluso en la primera/última semana del mes, cuando esa semana solo tiene 1-2 días hábiles reales dentro del mes visible (el resto cae en el mes adyacente) — producía % de cumplimiento engañoso. Corregido a `(targetWeeklyHours/5)*workDays`, igual que el resto de métodos del servicio (`getMonthlyHours`, `getSpecificWeekStats`, etc.) ya hacían.
- Ejemplo verificado: semana 27/04–03/05 vista desde mayo 2026 — antes Target=40h, ahora Target=8h (1 día hábil real en mayo).
- **Decisión del auditor:** el rango de fechas mostrado en semanas de borde (ej. "27/04 – 03/05" en vista de mayo) se deja sin recortar — el cálculo de horas ya es correcto, y mostrar el rango real de la semana ayuda a la navegación.
- Misma mejora de UX que EMP-012: fila completa de la tabla "Weeks" navega a `EmployeeWeekStats` al hacer click en cualquier celda.

**Verificación:** Selenium (login real vía cookie de sesión inyectada + capturas de pantalla) para las 4 tickets. Tests del módulo Employee: 44/44 verdes (sin regresiones; no había tests previos para el widget).

**Deuda / pendiente:** ninguna abierta por estos tickets.

### SAF-PWA-001 / CLA-170 ✅ Done

**Commits:** `d958759` (impl) + `6cf8179` (fix tests) | **Fecha:** 2026-06-23

**Cambio:** `ProjectController::index()` eliminó try/catch SQL Server y fallback DEV-001/DEV-002.
Ahora consulta `intelligence_mirror_projects` con `leftJoin` a `intelligence_mirror_relations` → añade `relation_name: string|null` al contrato (aditivo, no breaking).

**Tests:** 5 casos — con/sin relación, inactivo excluido, mirror vacío, no-import-Cafca.

**Riesgo operativo documentado:** frescura de proyectos depende del job de sync del mirror. Si el sync falla, el listado de la PWA queda desactualizado.

---

### SAF-NNN — Email reminder semanal a project_managers inactivos ✅ Done

**Commits:** `ff79b73` (impl) + `9600825` (URL PWA) + `6cf8179` (fix tests) | **Fecha:** 2026-06-23

**Archivos creados:**
- `Modules/Safety/Services/InspectionReminderService.php` — user-centric, `withTrashed()`, gracia 7 días, boundary `>= 30`
- `Modules/Safety/Emails/InspectionReminderMail.php`
- `Modules/Safety/resources/views/emails/inspection-reminder.blade.php` — NL, dos ramas de copy
- `Modules/Safety/Console/NotifyInactiveManagersCommand.php` — `safety:notify-inactive-managers [--days] [--dry-run]`
- `Modules/Safety/tests/Feature/NotifyInactiveManagersCommandTest.php` — 9 tests / 21 assertions ✅

**Schedule:** lunes 09:00 + `withoutOverlapping()` (sin colisión con `CheckSafetyComplianceCommand` en 08:00).

**Deploy:** requiere `SAFETY_PWA_URL=https://service.claesen-verlichting.be/` en `.env` de producción.

---

### Deudas técnicas Safety — pendientes de ticket

| Deuda | Descripción | Prioridad |
|-------|-------------|-----------|
| **SAF-DEBT-001** | ✅ Done `80f0385` — `MirrorRelation::$incrementing = false`. Workaround `DB::table()` en `ProjectControllerTest` revertido; `BillingGuardianOverdueTest` también se beneficia. | — |
| **SAF-DEBT-002** | Congelar tiempo en tests de frontera de `NotifyInactiveManagersCommandTest` — casos 3, 4, 5 usan `Carbon::now()->subDays(N)` sin `Carbon::setTestNow()`. En condiciones normales pasan, pero pueden ser flaky si el test cruza medianoche o en CI con reloj rápido. | Baja |

### SAF-019 — Payload fingerprint (idempotency hash) 🚧 Commit aprobado, cierre pendiente

**Commit:** `19b7cf1` | **Fecha:** 2026-06-21

**Archivos modificados:**
- `Modules/Safety/Models/Inspection.php` — `payload_hash` en `$fillable`
- `Modules/Safety/Http/Requests/StoreInspectionRequest.php` — `answers.*.question_id`: `integer` + `distinct` + `Rule::exists` scoped a `checklist_id`; `withValidator()` rechaza fotos huérfanas y keys no-numéricas (422)
- `Modules/Safety/Http/Controllers/InspectionController.php` — `canonicalPayload()` + `computeHash()` + `idempotentResponse()`; SHA-256 computado post-validación pre-transacción; `UniqueConstraintViolationException` capturada solo para `safety_inspections_user_idempotency_unique`
- `Modules/Safety/database/migrations/2026_06_21_120000_add_payload_hash_to_safety_inspections.php` — `payload_hash VARCHAR(64) NULL` ✅ aplicada

**Comportamiento de `payload_hash = NULL` (registros legacy):**
> Devuelve 200 para preservar compatibilidad con registros anteriores a SAF-019. **No verifica igualdad del payload** — un payload diferente con la misma `idempotency_key` también recibirá 200 si el registro es legacy. Este comportamiento es deliberado y está documentado en `idempotentResponse()`.

**Pendiente antes de cierre:**
1. Crear ticket en Linear: `SAF: fix 5 pre-existing test failures after employees worker migration` — `78327ae` cambió `present_workers.*` de `exists:users,id` a `exists:employees,id` sin actualizar tests. Afecta: `InspectionAuthStoreIndexTest` (3), `InspectionPhotoStorageFailureTest` (2).
2. Confirmar que CI acepta o excluye justificadamente los 5 fallos (no son regresiones de SAF-019 — verificado con `git stash`).
3. Rotación de API key de Linear verificada externamente (401/403 key antigua, nueva key almacenada como secreto, no expuesta en código/logs/Git).

---

### Sesión 2026-06-30 — Employee module: tests, mirror fix, cache, EmployeeResource ✅ Done

**Commits (esta sesión):**

| Hash | Descripción |
|------|-------------|
| `1a88873` | fix(Employee): Livewire dispatch + Alpine window event for chart data |
| `b230f2f` | feat(Employee): EmployeeHoursSummaryWidget — top-3 + stats en dashboard |
| `407d396` | fix(Employee): top-3 cards layout (flex) + Tailwind @source Modules scan |
| `37ab1ff` | fix(Employee): FQCN `\Carbon\Carbon` en employee-month-stats blade |
| `4b8feec` | fix(Employee): wire:navigate en todos los links internos (SPA navigation) |
| `1e157a0` | test(Employee): 44 tests — auth, rankings, time stats, MirrorLabor enrichment |
| `08b7453` | fix(Employee): ProjectRepository.find/getProjectsByIds → MirrorProject (MySQL) |
| `eb74d5a` | feat(EMP-A): cache rankings — `Cache::remember()` con TTL adaptativo (30min actual / 6h histórico) |
| `f59f19a` | feat(EMP-B): EmployeeResource migrado a módulo Employee + Hours sub-nav tab |
| `626d15b` | docs(handoff): 2026-06-30 — cache rankings + EmployeeResource → Employee module |

**Smoke test browser (2026-06-30): ✅ aceptado**
- Sub-nav 4 tabs visible (Details / Edit / AI Performance / Hours)
- Tab Hours carga `/{record}/hours`, muestra cards totals + tabla semanal con % consecución
- Navegación prev/next mes operativa
- Sin errores 500

**Cambios relevantes:**

**Tests (1e157a0 + 08b7453):**
- 44 tests en `Modules/Employee/tests/Feature/` — todos verdes, sin mocks, contra mirror real
- `ProjectRepository::find()` y `getProjectsByIds()` usan `MirrorProject` (MySQL `intelligence_mirror_projects`) — eliminada dependencia sqlsrv en endpoints day/week stats

**Cache rankings (eb74d5a):**
- `EmployeeDashboardRankingService::getTopEmployees()` envuelve cómputo en `Cache::remember()`
- TTL adaptativo: rangos históricos (fin < inicio de mes actual) → 6h; mes en curso → 30 min
- Subconjuntos con `$employeeIds` explícito bypass cache (espacio de claves ilimitado)
- Cache key: `'employee.rankings.' . md5($startDate . $endDate)`

**EmployeeResource → módulo Employee (f59f19a):**
- `Modules/Cafca/Filament/Resources/EmployeeResource.php` **eliminado** — evita conflicto de rutas
- `Modules/Employee/Filament/Resources/EmployeeResource.php` — propietario canónico de todas las rutas `/employees/*`
- Las 5 páginas Cafca (List, Create, View, Edit, EmployeeAnalytics) y `EmployeesTable` ahora referencian el nuevo resource
- `Modules/Employee/Filament/Resources/Employees/Pages/EmployeeAnalytics.php` — copia en namespace Employee
- `Modules/Employee/Filament/Resources/Employees/Pages/EmployeeHoursPage.php` — nueva sub-nav "Hours" (`/{record}/hours`)
- Vista blade `employee-hours-page.blade.php` — resumen mes (laden/werf/transport/km) + tabla semanal con % consecución
- Sub-nav de 4 tabs por empleado: **Details | Edit | AI Performance | Hours**
- `ViewEmployee::getHeaderActions()` — botón "View Hours" actualizado para apuntar a `EmployeeHoursPage::getUrl()`
- `Performance\ProjectInsightResource` — referencia a `EmployeeResource::getUrl()` actualizada al nuevo namespace

---

### Sprint EMP — Estabilización /employees 🚧 En curso

**Issues creados:** 2026-06-21. Orden aprobado: EMP-001 → EMP-004 → EMP-002 → EMP-005 → EMP-003 → EMP-006 → EMP-007

| Ticket | Linear | Título | Archivo principal | Depende de | Estado |
|--------|--------|--------|-----------------|-----------|--------|
| EMP-001 | CLA-162 | Retirar alerta Watchdog falsa | `EmployeeInfolist.php:71-96` | — | ✅ Done `39c1e07` |
| EMP-004 | CLA-163 | Eliminar botón "View archives" | `employee-project-timeline.blade.php:124` | — | ✅ Done `5f0ec35` |
| EMP-002 | CLA-164 | `uren_per_week` → estado unknown | `EmployeePerformanceService.php` + infolists | — | ✅ Done `ef513c7` |
| EMP-005 | CLA-165 | Eliminar llamada duplicada Livewire | `EmployeeProjectTimeline.php` | — | ✅ Done `bc9ff40` |
| EMP-003 | CLA-166 | Diferenciar 3 estados ERP/datos | `EmployeeProjectTimeline.php` + blade | EMP-005 | ✅ Done `176da75` |
| EMP-006 | CLA-167 | Locale configurable prompt Gemini | `TechnicianAnalysisService.php:56` | — | ✅ Done `8d5c27a` |
| EMP-007 | CLA-168 | Auditoría permisos Analytics | `EmployeeAnalytics.php` (solo lectura) | EMP-002, EMP-003 | ✅ Done (Status Quo) |

**Decisiones del auditor para este sprint:**
- EMP-001: no eliminar claves de traducción, verificar uso global primero
- EMP-002: `null` (no `0`) cuando `uren_per_week <= 0`; sin dependencia de EMP-003
- EMP-003: captura `\Throwable` para conexión caída; re-throw si no es error de conexión/PDO; `hasHistory` en `mount()` una sola vez
- EMP-005: verificar con `DB::connection('sqlsrv')->enableQueryLog()` sobre la conexión correcta
- EMP-006: sin migración `insight_locale`; locale canónico nl/en con fallback nl
- EMP-007: discovery puro; si exige código → ticket EMP-007b separado

**NO GO explícito del auditor para este sprint:**
- Leaderboard, anomaly detection individual, coste por empleado, scheduler IA, QR con token de sesión
- Compliance Safety en perfil (pendiente confirmar relaciones worker/employee)
- Certificaciones, disponibilidad: requieren discovery previo

**Pendiente producción (CLA-161):**
```bash
php artisan migrate
php artisan mailing:backfill-preference-snapshots  # dry-run primero
php artisan mailing:backfill-preference-snapshots --apply
# reiniciar workers
```

### MAI-PREF-001 / CLA-161 — Enforcement de Category Preferences ✅ Done

| Archivo | Cambio | Estado |
|---------|--------|--------|
| `Mailing/database/migrations/2026_06_20_000014_*` | `preference_category` en `email_templates` | ✅ |
| `Mailing/database/migrations/2026_06_20_000015_*` | `template_category_snapshot` + `preference_category_snapshot` en `mailing_campaigns` | ✅ |
| `Mailing/Models/EmailTemplate.php` | `preference_category` en `$fillable` + `booted()` saving hook | ✅ |
| `Mailing/Models/Campaign.php` | `buildSnapshotFrom()` + guard en `transitionTo(APPROVED)` | ✅ |
| `Mailing/Jobs/ExecuteCampaignJob.php` | `assertValidSnapshots()` + skip order correcto + sin fallback a mutable template | ✅ |
| `app/Contracts/MarketingCampaignInterface.php` | `bool $isCommercial = true` propagado | ✅ |
| `Mailing/Emails/ProspectCampaignMail.php` | `List-Unsubscribe` headers condicionales | ✅ |
| `Mailing/Services/MicrosoftGraphMailer.php` | Firma actualizada con `isCommercial` | ✅ |
| `Mailing/Services/SaaSMailer.php` | Firma actualizada | ✅ |
| `Mailing/Filament/.../EmailTemplateForm.php` | Select `preference_category` visible solo para COMMERCIAL | ✅ |
| `Mailing/Filament/.../CampaignForm.php` | `afterStateUpdated` usa `buildSnapshotFrom()` + Hidden fields para nuevos snapshots | ✅ |
| `Mailing/Console/BackfillPreferenceSnapshotsCommand.php` | Nuevo — dry-run por defecto, `--apply` para commit | ✅ |
| `Mailing/Providers/MailingServiceProvider.php` | Registra BackfillPreferenceSnapshotsCommand | ✅ |
| `Mailing/database/seeders/Led2027HighConversionTemplatesSeeder.php` | `preference_category => 'offers'` en los 3 templates | ✅ |
| `Mailing/database/factories/CampaignFactory.php` | Defaults con snapshots + estados `commercial()`, `transactional()`, `withoutSnapshots()` | ✅ |
| `Mailing/database/factories/EmailTemplateFactory.php` | `preference_category` default + estados `asOffers()`, `asNewsletter()`, `asEvents()`, `transactional()` | ✅ |
| `Mailing/lang/en/resource.php` + `nl/resource.php` | Claves `preference_category`, `preference_category_helper`, `template_invalid_pref_category` | ✅ |
| `Mailing/tests/Feature/CategoryPreferenceEnforcementTest.php` | Nuevo — 20 tests | ✅ |
| `Mailing/tests/Feature/BackfillPreferenceSnapshotsCommandTest.php` | Nuevo — 9 tests | ✅ |
| `Mailing/tests/Feature/ListUnsubscribeTest.php` | +3 tests `isCommercial` | ✅ |
| `Mailing/tests/Feature/CampaignWorkflowTest.php` | 2 tests con template vinculado para `transitionTo(APPROVED)` | ✅ |
| `docs/ai/known-risks.md` | Risk "enforcement" cerrado | ✅ |

**Secuencia de deploy (producción):**
```bash
# 1. Parar workers antes de migrate
# 2. php artisan migrate
# 3. php artisan mailing:backfill-preference-snapshots  (dry-run, revisar output)
# 4. php artisan mailing:backfill-preference-snapshots --apply
# 5. Reiniciar workers
```

**Tests verificados (2026-06-20):** 77 passed / 134 assertions — CategoryPreferenceEnforcement (22), BackfillPreferenceSnapshots (9), ListUnsubscribe (12+2 skip), CampaignWorkflow (20), ExecuteCampaignJobCounter (14). ✅ GO técnico aprobado.

### SAF-017→022 — Soft Delete Seguro de Inspecciones ✅ Done

| Ticket | Título | Commit | Estado |
|--------|--------|--------|--------|
| SAF-017 | SoftDeletes trait en Inspection model | `08f5f4a` | ✅ Done |
| SAF-018 | InspectionPolicy — delete/restore/forceDelete | `08f5f4a` | ✅ Done |
| SAF-019 | Filament — Archiveren action + RestoreAction + TrashedFilter | `08f5f4a` | ✅ Done |
| SAF-020 | getEloquentQuery() withoutGlobalScope SoftDeletingScope | `08f5f4a` | ✅ Done |
| SAF-021 | API: route model binding devuelve 404 para deleted | `08f5f4a` | ✅ Done |
| SAF-022 | Tests InspectionSoftDeleteTest (11 tests) + fix InspectionPhotoStorageFailureTest | `08f5f4a`/`34e88fd` | ✅ Done |

**Fix pre-existente documentado (34e88fd):**
- `Queue::fake()` → `Bus::fake()` (inline, no en setUp) — `Queue::assertDispatched()` no existe en Laravel 12
- `"photos[{id}]" => file` → `'photos' => [id => file]` — brackets literales no activan `hasFile("photos.N")`; array anidado sí
- Mockery mock vía `Storage::set()` para simular fallo de disco (path-based tricks inútiles en Docker/root)

### BI-PROJ — Vista de Águila

| Ticket | Título | Commit | Estado |
|--------|--------|--------|--------|
| BI-PROJ-01 | Auditoría ProjectInsightResource — confirma nueva page en Intelligence | (docblock en clase) | ✅ Done |
| BI-PROJ-02 | `ProjectIntelligenceDetail` page MVP — `/project-detail/{projectId}` | `7db83ae` | ✅ Done — GO |
| BI-PROJ-03 | Wire "Projectdetails openen" button en billing-control modal | `a0e1007` | ✅ Done |
| BI-PROJ-04 | Quitar class_exists guard, importar BillingAlert directo | `3af5fc8` | ✅ Done |
| BI-2B-UX-09 | Billing Control — secciones por pregunta de negocio (tabs eliminadas) | `8f20e3f` | ✅ Done |
| BI-2B-UX-10 | Desactivar partial_payment + DismissPartialPaymentAlerts command | `8f20e3f` | ✅ Done |
| BI-2B-UX-13 | Docs actualizados — partial_payment eliminado, secciones documentadas | `8f20e3f` | ✅ Done |
| BI-2B-UX-14 | Maandstatus card arriba — absorbe banner rojo, KPI grid | (este commit) | ✅ Done |
| BI-2B-UX-15 | Vervallen facturen — top 10 + "Toon alle" + summary stats | (este commit) | ✅ Done |
| BI-2B-UX-16 | Compact empty states (40–60px, single line) — Afgesloten + Creditnota's | (este commit) | ✅ Done |
| BI-2B-UX-17 | Quick nav anchors + section IDs | (este commit) | ✅ Done |

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
| **Safety** | ✅ Sprint completado (SAF-001 a SAF-022) + Fase 1A Adopción PWA completada | `main` | `docs/safety-sprint-linear-tickets.md` |
| **Performance** | 🚧 ~85% | `main` | Ver `CLAUDE.md` |
| **Intelligence / BI** | ✅ Sprint 1 ✅ Sprint 2B — PR #6 pendiente merge; BI-PROJ-02 ✅ (Vista de Águila) | `feature/bi-project-intelligence-detail` | `docs/bi-sprint-plan.md` |
| **Prospects** | 🚧 ~80% (PROS-BUG-001+002 cerrados, FAB mailing operativo, sync dashboard exception feed) | `main` | Ver `CLAUDE.md` |
| **Cafca** | ✅ ~90% | `main` | Ver `CLAUDE.md` |
| **Core** | ✅ ~99% | `main` | Ver `CLAUDE.md` |

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

### Topología de red

```
Internet → sbapu03 (192.168.60.10) nginx edge
               └─ proxy_pass 127.0.0.1:9443
                    └─ autossh túnel inverso → prod-priv-01 (192.168.254.52):443
                         └─ nginx local → PHP-FPM 8.4 → Laravel
```

CORS gestionado en sbapu03 nginx (cors-map.conf + proxy_hide_header). No en Laravel HandleCors.

### Resumen deploy

```
git push origin main
       ↓
GitHub Actions — "Build Laravel release" (composer --no-dev, npm build, tar.gz → GitHub Releases 'production-latest')
       ↓
ssh bert@192.168.254.52
bash /opt/claesen/scripts/deploy.sh [branch]
```

Deploy al servidor es **manual** — CI construye el artefacto, bert decide cuándo activarlo.

### Lo que hace deploy.sh (`/opt/claesen/scripts/deploy.sh`)

Capistrano-style: releases dir + `current` symlink + shared `.env` y `storage`.

1. Descarga `release.tar.gz` de GitHub Releases (`gh release download production-latest`)
2. Extrae en `/srv/www/claesen/releases/<timestamp>`
3. Symlinks: `shared/.env` → `releases/<ts>/.env`, `shared/storage` → `releases/<ts>/storage`
4. `chown bert:www-data shared/.env && chmod 640` — www-data puede leer .env como grupo
5. `composer install --no-dev`
6. `npm ci && npm run build` (assets)
7. `php artisan migrate --force`
8. `php artisan optimize:clear && filament:upgrade && optimize`
9. `sudo chown -R www-data:www-data releases/<ts>` + `chmod -R 775`
10. `sudo -u www-data php artisan config:cache` — genera config.php legible por www-data
11. `sudo rm -rf current && sudo ln -s releases/<ts> current`
12. `sudo systemctl reload php8.4-fpm`
13. `supervisorctl restart claesen-worker:* claesen-scheduler`

### Scripts de servidor (versionados en `infrastructure/`)

| Script | Ruta producción | Propósito |
|--------|-----------------|-----------|
| `deploy.sh` | `/opt/claesen/scripts/deploy.sh` | Deploy principal (ver arriba) |
| `backup-mysql.sh` | `/opt/claesen/scripts/backup-mysql.sh` | mysqldump con `--no-tablespaces` (sin PROCESS privilege) |
| `backup-files.sh` | `/opt/claesen/scripts/backup-files.sh` | Restic backup `/srv/www/claesen`, nginx, ssl |
| `backup-all.sh` | `/opt/claesen/scripts/backup-all.sh` | Orquesta backup-mysql + backup-files + ntfy notify |
| `monitor.sh` | `/opt/claesen/scripts/monitor.sh` | Checks servicios, disco (>85%), RAM (<10% libre) |
| `notify.sh` | `/opt/claesen/scripts/notify.sh` | Push ntfy.sh — wrapper de notificaciones |

Config env en `/etc/claesen-backup.env` y `/etc/claesen-notify.env` (permisos `root:bert 640`).
Backups en `/var/backups/claesen/` (permisos `root:bert 770`).

### Nginx sbapu03 (versionado en `infrastructure/nginx/sbapu03/`)

| Archivo | Propósito |
|---------|-----------|
| `cors-map.conf` | `map $http_origin $cors_allowed_origin` — allowlist de 4 orígenes + localhost:5173 |
| `backend.claesen-verlichting.be.conf` | Proxy + CORS edge: OPTIONS→204, proxy_hide_header, add_header always, proxy_redirect |

### Notas operativas

- `.env` en producción: `bert:www-data 640` — www-data lee por grupo; bert puede editar directamente.
- Si deploy falla después de step 11 (symlink): `php artisan up` desde `/srv/www/claesen/current`.
- `gh` CLI autenticado como `cubanote816` en `/home/bert/.config/gh/hosts.yml`.
- Para editar nginx en sbapu03: `sudo tee /etc/nginx/sites-available/<archivo>` + `sudo nginx -t && sudo nginx -s reload` (ambos NOPASSWD para bert).

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
| 2026-06-29 | fix(perf) | Done — `EmployeePerformanceService` + `EmployeeInfolist`: todas las queries `Labor` (sqlsrv → `followup_labor_analytical`) reemplazadas por `MirrorLabor` (`intelligence_mirror_labor`) y `MirrorProject` (`intelligence_mirror_projects`). Afecta: `getShortTrend`, `getStatsForPeriod`, `getDailyStats`, `hasAnyLaborHistory`, `getComparativeRanking`, `getTeamPosition`, `getTemporalProjectDetails`, `active_projects_summary`. `categorizeLaborEntry` desacoplado del tipo `Labor` (duck typing). Commit `8ef70ce`. |
| 2026-06-29 | SAF reminder | Verificado — `safety:notify-inactive-managers` listo para producción. 9/9 tests ✅. `SAFETY_PWA_URL` confirmado en `.env` producción. Scheduler: lunes 09:00 `withoutOverlapping()`. Recomendado `--dry-run` antes del primer lunes. |
| 2026-06-28 | CLA-182 | Done — `POST /api/v1/auth/change-password` para cuentas locales (microsoft_id null). 403 para cuentas Microsoft. Valida `current_password` vía Hash::check. Revoca todos los tokens Sanctum excepto el actual. `GET /api/v1/me` añade `auth_provider: "local"\|"microsoft"` para que el frontend sepa si mostrar Beveiliging. 6 tests / 12 assertions ✅. Commit `32ae7fe`. Nota test: `auth()->forgetGuards()` entre requests secuenciales para evitar cache de RequestGuard entre requests del mismo test. |
| 2026-06-28 | CLA-178 | Done — Rediseño emails Safety: `inspection-report.blade.php` (tabla, inline styles, logo, banda azul/roja por tipo, hero, badge, firma) + `inspection-reminder.blade.php` (mismo patrón, banda ámbar, alert box, CTA). Commits `74fef44` + `bdf77c4`. Fix colateral: nota indicativa CTOR en `CampaignMetricsWidget` + clave `ctor_note` en lang EN/NL. Commit `11cca98`. 116/116 tests Safety ✅ (3 fallos preexistentes Safety-auth no relacionados). |
| 2026-06-28 | i18n | Done — Auditoría y corrección completa de strings de UI en backoffice (Bloques A→D). Eliminados todos los strings en español, todos los ternarios `$nl ? ... : ...`, y todos los labels NL/EN hardcodeados. Ahora toda la UI usa `__()` con `app()->getLocale()`. Nuevos ficheros: `Modules/Intelligence/lang/{nl,en}/{billing,offer_simulator,bi_config}.php`, `Modules/Performance/lang/{nl,en}/projects.php`. Actualizados: `lang/{nl,en}/navigation.php` (12 grupos sidebar), `Modules/Safety/lang/{nl,en}/inspections.php` (columnas, tipos, badges, acciones, secciones). PHP afectado: `SafetyAdoptionOverviewWidget`, `InspectionResource`, `MonthlyBillingControlPage`, `OfferSimulator`, `BiConfigPage`, `ProjectResource` + 7 resources para grupos de navegación. Commit `d70f318`. 216/216 tests ✅ (31 fallos preexistentes en Mailing/Safety-auth/Website no relacionados). |
| 2026-06-27 | INFRA | Done — CORS corregido en sbapu03 nginx: `cors-map.conf` + `proxy_hide_header` eliminan duplicados ACAO; preflight OPTIONS→204 sin hit PHP. `MissingAppKeyException` resuelto: `shared/.env` → `bert:www-data 640`; `deploy.sh` corre `sudo -u www-data config:cache` post-chown. `mysqldump` saneado (`--no-tablespaces`, sin `--events`). 3 deploys limpios consecutivos. Scripts versionados en `infrastructure/`. Commit `667416a`. |
| 2026-06-27 | CLA-181 | Done — Migración global auth browser-first: Sanctum SPA cookie session. `statefulApi()` + CORS `supports_credentials=true` + Safety login/logout/me por cookie HttpOnly sin token + OAuth callback sin Bearer en URL + `loginSpa()` en Core para SPAs + `EnsureSafetyAccess` desacoplado de token ability para sesión + `logout()` tolerante a `TransientToken` + `localhost:5173` en stateful domains + `.env.example` documentado. Bearer legacy intacto para FieldOps/Sport. 251/251 tests ✅. Commit `80e3f1e`. Pendiente frontend: `GET /sanctum/csrf-cookie` antes de POST login, `withCredentials: true`, retirar `localStorage.auth_token`. `SESSION_DOMAIN` y `SESSION_SAME_SITE=none` en `.env` producción si frontend es cross-site. |
| 2026-06-24 | CLA-174 | Done — `project_address_text` (Projectadres) añadido al mirror y al endpoint Safety projects. Batch-load desde `txt.txt` vía `project.project_address = txt.txt_id`. Normalización null si vacío/whitespace. Contrato: `{id, name, descr, project_address_text, relation_name}`. 7 tests / 19 assertions. Commit `526b0b8`. Backfill: `php artisan intelligence:sync-mirror` post-deploy. |
| 2026-06-24 | FO-002 / CLA-173 | Done — `project.descr` añadido al mirror (migración + sync) y al endpoint Safety projects. Contrato: `{id, name, descr, relation_name}`. 5 tests / 15 assertions. Commit `50fc4eb`. |
| 2026-06-24 | FO-001 / CLA-172 | Done — Filament admin FieldOps (FoClientResource, TerrainTypeResource, StructureTypeResource), TranslateModelAttributesJob (Gemini nl/en/fr/de, ai_translation_status), SetLocaleFromHeader middleware en rutas v1/fieldops/*. 6 tests / 14 assertions. Commit `78e66df`. |
| 2026-06-23 | C.6a | Done — `GET /complexes?client_id=X` y `GET /structures?terrain_id=X`. Ambos filtros con `when()` + `whereHas()`. 5 tests nuevos / 15 assertions. 112/270 total FieldOps. Commit `b8b0205`. Desbloquea C.6b (frontend cutover). |
| 2026-06-23 | C.5 | Done — LuminaireFrame CRUD (structure_ids triple-case) + Luminaire CRUD (serial_number unique, frame_position auto-recalculado al cambiar frame, cross-validate type↔subgroup, info locale-merge). 35 tests / 95 assertions. 107/255 total FieldOps. Commit `e4452cf`. |
| 2026-06-23 | C.4 | Done — Structure CRUD. terrain_ids triple-case explícito (`absent→no-op / null→detach / array→sync`) usando `$request->has()`. info locale-merge. external_*_id como bridge opaco. 28 tests / 59 assertions. 72/160 con C.2+C.3+C.4. Commit `b2ff1c4`. |
| 2026-06-23 | C.3 | Done — Terrain CRUD (GET/POST/PUT/PATCH/DELETE). Locale validation `array:nl,en,fr,es`. Update merge parcial de traducciones. `complex_id` inmutable en update. 24 tests / 54 assertions. Commit `fbfaf6d`. |
| 2026-06-23 | C.2 | Done — Complex CRUD (POST/PUT/PATCH/DELETE) + RouteServiceProvider fix + factories + 20 tests. Flakiness de arranque documentada. Próximo: C.3 auditor gate. |
| 2026-06-23 | SAF-ADOPT / CLA-169 | Done — Fase 1A Adopción PWA completada. Rollups diarios con `project_id='GLOBAL'`, denominador `enabled_users` anclado estrictamente a los roles del middleware `EnsureSafetyAccess` (project_manager, super_admin, admin). Feature tests funcionales implementados validando el endpoint completo y previniendo duplicidad en `idempotency_key`. Commit `43089fb`. |
| 2026-06-22 | CLA-168 | Done — EMP-007: Discovery auditoría permisos cerrado. Decisión de negocio: Status Quo. El acceso a `EmployeeAnalytics` se restringe a `super_admin` y `admin` porque los insights IA y burnout son datos muy sensibles. No se modifica código ni se abre a managers/empleados sin separar antes datos operativos de sensibles. Sin commit de código. |
| 2026-06-22 | CLA-164 | Done — EMP-002: `calculateAchievementRate()` devuelve `null` (no `0%`) cuando `uren_per_week` es `null` o `<= 0`. `getDailyStats()` sin fallback `?? 0`. `aggregateStats()` docblock explicita baseline 7,6h vs contrato. Widget Stats: stat gris `Niet berekenbaar` cuando rate null; stat semanal usa clave `compliance_operational` (`Basis 7,6u`). Chart widget: línea target omitida cuando `uren_per_week` es null/0. Traducciones NL+EN (`achievement_unknown`, `compliance_operational`). Test sin `RefreshDatabase` (seam en memoria, determinista). 7 archivos, 8 tests / 15 aserciones ✅. Commit `ef513c7`. |
| 2026-06-22 | CLA-167 | Done — EMP-006: locale configurable para prompt Gemini en `TechnicianAnalysisService`. Config `performance.ai_insight_locale` (nl/en, fallback nl). Cache key v2 (`md5`). Prompt completo NL/EN sin texto en español. `PERFORMANCE_AI_LOCALE` en `.env.example`. 4 archivos, 15 tests / 59 aserciones ✅. Commit `8d5c27a`. |
| 2026-06-22 | CLA-166 | Done — EMP-003: diferenciar estados `erp_unavailable`, `no_period_activity`, `no_history` y `ready` en `EmployeeProjectTimeline`. Clasificador SQLSTATE (clase 08, HYT00/HYT01/IM002/IM014 + fallback mensajes). `hasAnyLaborHistory()` en `EmployeePerformanceService` para mockability. Blade 3 paneles PHP `@if`. 6 archivos, 11 tests / 50 assertions ✅. Smoke visual ✅. Commit `176da75`. |
| 2026-06-22 | CLA-165 | Done — EMP-005: caché `#[Locked] $cachedProjects` elimina segunda query SQL Server en `render()`. 2 archivos (componente + test). 4 tests/15 assertions ✅. Smoke visual ✅. Commit `bc9ff40`. |
| 2026-06-22 | CLA-163 | Done — EMP-004: eliminar botón "View archives" de `employee-project-timeline.blade.php:124`. 1 archivo, 1 línea. view:cache ✅. Smoke visual ✅ (sesión legítima Filament). Commit `5f0ec35`. |
| 2026-06-22 | CLA-162 | Done — EMP-001: eliminar alerta Watchdog falsa de `EmployeeInfolist.php:70-96`. 1 archivo, bloque eliminado. Commit `39c1e07`. |
| 2026-06-20 | CLA-161 | Done — MAI-PREF-001 enforce category preferences. Commits `80660d6`+`02a143d`. 77 tests / 134 assertions. Deploy pendiente: `migrate` + `mailing:backfill-preference-snapshots --apply`. |
| 2026-06-16 | CLA-159 | Done — Author audit metadata en `safety_questions`: migración FK `created_by_user_id`/`updated_by_user_id`, QuestionObserver, relaciones en modelo, API `show`/`active` devuelven `created_by`/`updated_by {id,name}`. 7 tests nuevos. Commit `a096243`. |
| 2026-06-16 | CLA-160 | Done — `safety:backfill-question-authors --apply` ejecutado en producción. 15 preguntas → Orelvys, 3 → Bert (creadas el 14-jun), 17 → updated_by Bert, ID 18 updated_by=null. Commits `7fc4a03`+`2d6938f`. |
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

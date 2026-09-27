# CLA-581: cutover ejecución de work orders Sport → Core

## Objetivo

Migrar a Core (panel Filament) el flujo de ejecución de work orders que hoy solo existe en Claesen-Sport (PWA de técnicos), como parte del cutover FO-006. Offline y cámara nativa quedan explícitamente fuera de alcance (ver CLA-581 en Linear para el razonamiento completo).

## Por qué

CLA-328 confirmó la deprecación de Claesen-Sport. La exploración técnica (2026-09-22) encontró que 2 de 3 capacidades faltantes en Core son buildable como features Filament genuinas (adjuntar fotos, flujo de ejecución vía Wizard); offline es una pared arquitectónica de Livewire sin solución dentro de Filament, así que queda fuera. El usuario confirmó dar acceso al panel al rol `technician` con navegación restringida.

## Alcance autorizado

- `Modules/Core/Models/User.php`: agregar `technician` a `hasPanelAccess()`.
- Nueva página Filament `Modules/FieldOps/Filament/Pages/MyWorkOrders.php`: lista de órdenes asignadas al técnico logueado, acción "Iniciar" (`MaintenanceWorkOrderService::start()`), acción "Completar" vía `Wizard` (checklist/causa raíz/solución/fotos) que llama `MaintenanceWorkOrderService::submit()`.
- Fotos: subir contra el `maintainable` (Luminaire/ElectricalBoard) del work order, reusando `FieldOpsMediaController` existente — no se crea mecanismo nuevo.
- Tests de acceso/scope/regresión.
- Docs: `CLAUDE.md` (gaps FieldOps, regla #5) + `docs/ai/fieldops-maintenance-roadmap.md`.

**Fuera de alcance:** offline, cámara nativa (`capture` attribute), cualquier cambio a `FoMaintenanceWorkOrderResource` (admin, sin tocar).

## Constraints reusadas (no reinventar)

- `MaintenanceWorkOrderService::start()`/`::submit()` — máquina de estados ya completa y probada.
- `SubmitMaintenanceWorkOrderRequest` — contrato de campos y regla de autorización (`hasAnyRole(['super_admin','admin']) || $user->employee_id === $order->assigned_employee_id`) ya definidos, replicar la misma lógica de scope.
- `EditMaintenanceWorkOrder` (admin) tiene un formulario de revisión AWAITING_VALIDATION con los mismos campos — usar de referencia de UI.

## Checklist

- [x] T1: `hasPanelAccess()` incluye `technician` (`Modules/Core/Models/User.php`). También hubo que sacar `technician` del denylist explícito de `Modules\Core\Filament\Pages\Auth\Login::authenticate()` (CLA-363) — sin eso, `hasPanelAccess()` solo no alcanzaba, el login seguía fallando antes de establecer sesión.
- [x] T2: `MyWorkOrders` Page — lista scoped a `assigned_employee_id`, acción "Iniciar"
- [x] T3: `MyWorkOrders` Page — Wizard "Completar" (checklist/root_cause/solution_applied/fotos) → `submit()`
- [x] T4: auditados los 31 resources Filament reales del repo (no solo FieldOps) — todos con `canAccess()` explícito por rol excepto los 7 de T4b
- [x] T4b: **hallazgo durante T4** — 6 resources de OTROS módulos (`Performance\ProjectResource`, `Performance\ProjectInsightResource`, `Employee\EmployeeResource`, `Safety\ChecklistResource`, `Mailing\EmailTemplateResource`, `Prospects\ProspectResource`) no tienen `canAccess()` ni Policy registrada — Filament los deja abiertos por defecto a *cualquier* usuario con acceso al panel (confirmado leyendo `vendor/filament/filament/src/helpers.php::get_authorization_response` — sin policy y sin modo estricto, cae a `Response::allow()`). Esto ya es así hoy para `viewer`/`financial_manager`/`hr_manager` (gap preexistente, fuera de este ticket arreglarlo para ellos), pero agregar `technician` sin guard heredaría el mismo acceso — viola el requisito explícito de "navegación restringida". Fix mínimo y quirúrgico: agregar `canAccess()` a esos 6 resources que excluya solo `technician`, sin cambiar el comportamiento actual de ningún otro rol. **Ampliado a 7+1**: `Safety\InspectionResource` (Policy registrada pero sin método `viewAny()`, mismo efecto) y `Mailing\CampaignResource` (`canAccess()` propio pero `auth()->check()` sin excluir ningún rol) también necesitaron el mismo guard.
- [x] T5: tests — `Modules/FieldOps/tests/Feature/MyWorkOrdersFilamentTest.php` (6 tests nuevos) + `Modules/Core/tests/Feature/PanelAccessTest.php` actualizado. Suites completas corridas secuencialmente (evitando la colisión de DB `testing` compartida, ver memoria `feedback_sail_docker` — 3 corridas en paralelo al principio dieron falsos negativos masivos, `migrate:fresh` manual + re-run secuencial lo confirmó): **FieldOps 582/582, Core 125/125, Performance+Employee+Safety+Mailing+Prospects 523/523 — 1230 tests, 0 fallas.**
- [x] T6: docs. CLAUDE.md (gaps table FO-006 + regla #5) actualizado. `docs/ai/fieldops-maintenance-roadmap.md` **no** se toca — su propio alcance declarado son las Fases 0-5 del programa de mantenimiento/Claesen-Client (cerrado en CLA-276), FO-006 nunca estuvo ahí; el plan original asumió mal que aplicaba.

## Estado

TDD: no explícitamente activado en este proyecto; se siguen checks funcionales ordinarios (tests de Feature tras cada paso, no ciclo RED/GREEN estricto) — mismo patrón usado en el resto de la sesión (CLA-499→511).

Runner: `./vendor/bin/sail phpunit --testsuite=Modules --filter=FieldOps` (con `-u sail`, ver memoria `feedback_sail_docker`).

## Próximo paso

Todo implementado y verificado (T1-T6). Presentando diff/resumen al usuario, esperando GO técnico antes del commit.

# Safety Sprint — Tickets Linear (fuente de verdad)

> Generado desde Linear el 2026-05-24. No editar manualmente — refleja el contenido exacto de los issues CLA-5 a CLA-19.

---

## Mapa SAF ↔ Linear

| SAF | Linear | Título | Tipo | Prioridad |
|-----|--------|--------|------|-----------|
| SAF-001 | [CLA-5](https://linear.app/claesen-verlichting/issue/CLA-5) | Configuración base del módulo config/config.php | Chore | Alta |
| SAF-002 | [CLA-6](https://linear.app/claesen-verlichting/issue/CLA-6) | InspectionPolicy — Autorización por recurso | Security | Urgente |
| SAF-003 | [CLA-7](https://linear.app/claesen-verlichting/issue/CLA-7) | Cambio de disco: fotos y PDFs pasan a local privado | Security | Urgente |
| SAF-004 | [CLA-8](https://linear.app/claesen-verlichting/issue/CLA-8) | Rutas web admin para servir archivos Filament | Feature | Alta |
| SAF-005 | [CLA-9](https://linear.app/claesen-verlichting/issue/CLA-9) | GET /api/v1/safety/inspections/{id} — Detalle completo | Feature | Urgente |
| SAF-006 | [CLA-10](https://linear.app/claesen-verlichting/issue/CLA-10) | GET /api/v1/safety/inspections/{id}/pdf — Descarga API protegida | Feature | Urgente |
| SAF-007 | [CLA-11](https://linear.app/claesen-verlichting/issue/CLA-11) | GET /api/v1/safety/inspections/{id}/answers/{answerId}/photo — Streaming seguro | Feature + Security | Urgente |
| SAF-008 | [CLA-12](https://linear.app/claesen-verlichting/issue/CLA-12) | StoreInspectionRequest — Extracción de validación | Refactor | Alta |
| SAF-009 | [CLA-13](https://linear.app/claesen-verlichting/issue/CLA-13) | InspectionController::index() — Paginación y filtros | Feature | Alta |
| SAF-010a | [CLA-14](https://linear.app/claesen-verlichting/issue/CLA-14) | ComplianceService + refactor command | Feature + Refactor | Media |
| SAF-010b | [CLA-15](https://linear.app/claesen-verlichting/issue/CLA-15) | GET /api/v1/safety/compliance | Feature | Baja |
| SAF-011 | [CLA-16](https://linear.app/claesen-verlichting/issue/CLA-16) | Factories + HasFactory en modelos Safety | Test Infrastructure | Alta |
| SAF-012 | [CLA-17](https://linear.app/claesen-verlichting/issue/CLA-17) | Feature tests — Auth, Store e Index | Test | Alta |
| SAF-013 | [CLA-18](https://linear.app/claesen-verlichting/issue/CLA-18) | Feature tests — Show, PDF y Photo | Test | Alta |
| SAF-014 | [CLA-19](https://linear.app/claesen-verlichting/issue/CLA-19) | Tests rutas web admin /safety/files/... | Test | Media |

---

## Reglas globales

- Disco de fotos/PDFs: `config('safety.disk')` — valor esperado `local`
- Archivos existentes en `public` quedan como legado/dev; sin migración ni fallback
- Autorización por recurso con `Gate::authorize()`, sin cambiar el padre del controller
- `project_manager` solo ve recursos propios: `inspection.user_id === user.id`
- `super_admin` ve todos los recursos
- Tests y factories dentro de `Modules/Safety`
- No escribir código fuera de ticket aprobado

---

## Orden de ejecución

### Ola 1 — Sin dependencias

| SAF | Linear | Prioridad |
|-----|--------|-----------|
| SAF-002 | CLA-6 | Urgente |
| SAF-001 | CLA-5 | Alta |
| SAF-008 | CLA-12 | Alta |
| SAF-011 | CLA-16 | Alta |

### Ola 2 — Dependen de Ola 1

| SAF | Linear | Depende de | Prioridad |
|-----|--------|------------|-----------|
| SAF-003 | CLA-7 | SAF-001, SAF-002 | Urgente |
| SAF-009 | CLA-13 | SAF-001, SAF-002 | Alta |
| SAF-010a | CLA-14 | SAF-001 | Media |

### Ola 3 — Dependen de Ola 1 + 2

| SAF | Linear | Depende de | Prioridad |
|-----|--------|------------|-----------|
| SAF-005 | CLA-9 | SAF-002, SAF-003 | Urgente |
| SAF-006 | CLA-10 | SAF-002, SAF-003 | Urgente |
| SAF-007 | CLA-11 | SAF-002, SAF-003 | Urgente |
| SAF-004 | CLA-8 | SAF-002, SAF-003 | Alta |
| SAF-012 | CLA-17 | SAF-011, SAF-008, SAF-009 | Alta |
| SAF-010b | CLA-15 | SAF-010a, SAF-001, SAF-002 | Baja |

### Ola 4 — Dependen de Ola 1 + 2 + 3

| SAF | Linear | Depende de | Prioridad |
|-----|--------|------------|-----------|
| SAF-013 | CLA-18 | SAF-011, SAF-005, SAF-006, SAF-007 | Alta |
| SAF-014 | CLA-19 | SAF-004, SAF-011 | Media |

---

## Tickets — Alcance, cambios y criterios de aceptación

---

### SAF-001 · CLA-5 — Configuración base del módulo config/config.php

**Tipo:** Chore | **Prioridad:** Alta | **Depende de:** —

#### Alcance
Añadir constantes de negocio al config del módulo.

#### Cambios
- `disk => 'local'`
- `per_page => 15`
- `per_page_max => 50`
- `compliance_days => 30`

#### Criterios de aceptación
- `config('safety.disk')` retorna `'local'`
- `config('safety.per_page_max')` retorna `50`
- Sin constantes mágicas en controllers/command para estos valores

---

### SAF-002 · CLA-6 — InspectionPolicy — Autorización por recurso

**Tipo:** Security | **Prioridad:** Urgente | **Prerequisito de:** SAF-003, SAF-004, SAF-005, SAF-006, SAF-007

#### Cambios
- Crear `Modules/Safety/Policies/InspectionPolicy.php`
- Métodos: `view`, `downloadPdf`, `viewPhoto`
- Regla: `inspection.user_id === user.id` O rol `super_admin`
- Registrar con `Gate::policy()` en `SafetyServiceProvider::boot()`

#### Criterios de aceptación
- `project_manager` solo puede ver/descargar/foto de inspecciones propias
- `super_admin` puede ver/descargar/foto de cualquier inspección
- Usuario ajeno recibe 403
- Sin modificar el padre del controller

---

### SAF-003 · CLA-7 — Cambio de disco: fotos y PDFs pasan a local privado

**Tipo:** Security | **Prioridad:** Urgente | **Depende de:** SAF-001, SAF-002

#### Cambios
- `InspectionController::store()` guarda fotos con `Storage::disk(config('safety.disk'))`
- `GenerateSafetyPdfJob::handle()` guarda PDFs con `Storage::disk(config('safety.disk'))`
- Notificaciones apuntan a ruta web admin, no a `Storage::url()`

#### Criterios de aceptación
- Fotos nuevas van a `storage/app/safety-inspections/...`
- PDFs nuevos van a `storage/app/safety-inspections/...`
- `storage/app/public` no recibe archivos nuevos Safety
- Archivos no son accesibles por URL pública sin auth

---

### SAF-004 · CLA-8 — Rutas web admin para servir archivos Filament

**Tipo:** Feature | **Prioridad:** Alta | **Depende de:** SAF-002, SAF-003

#### Cambios
- Crear `SafetyFileController`
- `pdf(Inspection $inspection)`: stream desde disco local/config
- `photo(Answer $answer)`: stream desde disco local/config
- Ambos usan `Gate::authorize()` con InspectionPolicy
- Rutas web:
  - `GET /safety/files/inspections/{inspection}/pdf` → `safety.admin.pdf`
  - `GET /safety/files/answers/{answer}/photo` → `safety.admin.photo`
- Middleware: `auth`
- No middleware de rol en ruta; policy dentro del controller
- Filament `InspectionResource` apunta a estas rutas
- En `RepeatableEntry` answers, `ImageEntry` usa `answer_id`, no `inspection_id`
- Importar `Modules\Safety\Models\Answer` para type-hint del closure

#### Criterios de aceptación
- Admin con sesión puede ver foto/PDF autorizado
- Sin sesión activa redirige/login, no 200
- `project_manager` no puede acceder a recursos ajenos
- `super_admin` puede acceder a cualquier recurso

---

### SAF-005 · CLA-9 — GET /api/v1/safety/inspections/{id} — Detalle completo

**Tipo:** Feature | **Prioridad:** Urgente | **Depende de:** SAF-002, SAF-003

#### Cambios
- Añadir `InspectionController::show()`
- Eager load: `checklist`, `user`, `incidentWorker`, `presentWorkers`, `answers.question`
- `Gate::authorize('view', $inspection)`
- Respuesta incluye: `id`, `type`, `project_id`, `completed_at`, `pdf_status`, `pdf_url`, `inspector`, `incident_worker`, `present_workers`, `checklist`, `answers`
- `photo_url` apunta al endpoint API de foto si hay `photo_path`
- `pdf_status`: `pending` si `pdf_path` null, `available` si existe, `failed` si path existe pero archivo falta
- `pdf_url` null si `pdf_path` null; si tiene valor apunta al endpoint API aunque luego devuelva 404/failed

#### Criterios de aceptación
- Propietario → 200
- Usuario ajeno → 403
- ID inexistente → 404
- `super_admin` → 200 en cualquier inspección
- `photo_url` null si no hay foto

---

### SAF-006 · CLA-10 — GET /api/v1/safety/inspections/{id}/pdf — Descarga API protegida

**Tipo:** Feature | **Prioridad:** Urgente | **Depende de:** SAF-002, SAF-003

#### Cambios
- Añadir `InspectionController::downloadPdf()`
- `Gate::authorize('downloadPdf', $inspection)`
- Usar `Storage::disk(config('safety.disk'))`

#### Respuestas
- `pdf_path` null → 202 `{ pdf_status: 'pending' }`
- Archivo no existe → 404 `{ pdf_status: 'failed' }`
- Archivo existe → 200 `application/pdf` con `Content-Disposition: attachment`

#### Criterios de aceptación
- Inspector descarga propio PDF → 200
- Inspector descarga ajeno → 403
- PDF pendiente → 202
- Path definido pero archivo ausente → 404
- `super_admin` descarga cualquier PDF → 200

---

### SAF-007 · CLA-11 — GET /api/v1/safety/inspections/{id}/answers/{answerId}/photo — Streaming seguro

**Tipo:** Feature + Security | **Prioridad:** Urgente | **Depende de:** SAF-002, SAF-003

#### Cambios
- Añadir `InspectionController::servePhoto()`
- Validar que `answer.inspection_id === inspectionId`; si no, 404
- `Gate::authorize('viewPhoto', $inspection)`
- Usar `Storage::disk(config('safety.disk'))`
- Respuesta 200 con `Content-Type` real y `Cache-Control: private, max-age=900`

#### Criterios de aceptación
- Propietario ve foto → 200
- Usuario ajeno → 403
- Answer de otra inspección → 404
- Answer sin foto → 404
- `super_admin` ve cualquier foto → 200
- `show()` apunta a este endpoint

---

### SAF-008 · CLA-12 — StoreInspectionRequest — Extracción de validación

**Tipo:** Refactor | **Prioridad:** Alta | **Depende de:** —

#### Cambios
- Crear `Modules/Safety/Http/Requests/StoreInspectionRequest.php`
- Mover reglas actuales de `store()`
- Incluir `prepareForValidation()` para `answers` como JSON string en FormData
- Añadir `present_workers.* exists:users,id`
- Añadir `photos.* image|max:5120`
- `store()` usa `StoreInspectionRequest` y `validated()`

#### Criterios de aceptación
- `answers` como JSON string sigue funcionando
- Foto >5MB devuelve 422
- `store()` queda sin validación inline principal

---

### SAF-009 · CLA-13 — InspectionController::index() — Paginación y filtros

**Tipo:** Feature | **Prioridad:** Alta | **Depende de:** SAF-001, SAF-002

#### Cambios
- Reemplazar `limit(20)` por `paginate($perPage)`
- `per_page` desde query param, capped a `config('safety.per_page_max', 50)`
- Filtros: `type`, `project_id`, `from`, `until`
- Scope: `project_manager` solo propias; `super_admin` todas
- Respuesta paginator estándar Laravel

#### Criterios de aceptación
- `per_page=5` devuelve 5
- `per_page=999` devuelve máximo 50
- `type=incident` filtra incidentes
- `project_id` filtra proyecto
- `project_manager` no ve inspecciones ajenas
- `super_admin` ve todas
- Incluye metadata de paginación

---

### SAF-010a · CLA-14 — ComplianceService + refactor command

**Tipo:** Feature + Refactor | **Prioridad:** Media | **Depende de:** SAF-001

#### Cambios
- Crear `Modules/Safety/Services/ComplianceService.php`
- Método `getMissingInspections(int $days = null): Collection`
- Usa `config('safety.compliance_days')` por defecto
- Refactor `CheckSafetyComplianceCommand` para usar el service
- Manejar fallo de MirrorProject/SQL Server sin romper

#### Criterios de aceptación
- Command sigue funcionando igual
- Lógica de 30 días vive en el service
- Sin duplicar lógica

---

### SAF-010b · CLA-15 — GET /api/v1/safety/compliance

**Tipo:** Feature | **Prioridad:** Baja | **Depende de:** SAF-010a, SAF-001, SAF-002

#### Cambios
- Endpoint `GET /api/v1/safety/compliance`
- Usa `ComplianceService`
- Autorización: `super_admin` únicamente

#### Criterios de aceptación
- `super_admin` recibe 200 con `{ data: [...], count: N }`
- `project_manager` recibe 403
- Si MirrorProject no disponible, devuelve 200 `{ data: [], count: 0 }`

---

### SAF-011 · CLA-16 — Factories + HasFactory en modelos Safety

**Tipo:** Test Infrastructure | **Prioridad:** Alta | **Depende de:** —

#### Cambios
- Añadir `HasFactory` + `newFactory()` a `Inspection`, `Answer`, `Checklist`, `Question`
- Crear factories en `Modules/Safety/Database/Factories`:
  - `InspectionFactory` con estados `inspection()`, `incident()`
  - `AnswerFactory` con estado `nok()`
  - `ChecklistFactory` con estado `incident()`
  - `QuestionFactory`
- Verificar descubrimiento de tests del módulo; si no existe, añadir testsuite `Modules` en `phpunit.xml`

#### Criterios de aceptación
- `Inspection::factory()->create()` funciona
- `Inspection::factory()->inspection()->has(Answer::factory()->count(3), 'answers')->create()` funciona
- `php artisan test` ejecuta también tests del módulo
- `php artisan test --testsuite=Modules` funciona si se añade testsuite

---

### SAF-012 · CLA-17 — Feature tests — Auth, Store e Index

**Tipo:** Test | **Prioridad:** Alta | **Depende de:** SAF-011, SAF-008, SAF-009

#### Tests
- login success project_manager
- login wrong password → 401
- login rejected without safety role → 403
- store inspection → 201
- store validates required fields → 422
- store requires auth → 401
- index PM sees only own inspections
- index admin sees all
- index filters by type
- index paginates per_page
- index caps per_page max

#### Criterios de aceptación
- Tests verdes
- `RefreshDatabase`
- Roles con `firstOrCreate`

---

### SAF-013 · CLA-18 — Feature tests — Show, PDF y Photo

**Tipo:** Test | **Prioridad:** Alta | **Depende de:** SAF-011, SAF-005, SAF-006, SAF-007

#### Tests
- show full detail for owner
- show 403 foreign inspection
- show admin sees any
- show 404 nonexistent
- pdf 202 pending
- pdf serves file → 200 application/pdf
- pdf 403 foreign
- pdf 404 path set but missing
- photo serves owner
- photo 403 foreign
- photo 404 answer from different inspection
- photo 404 no photo

#### Criterios de aceptación
- `Storage::fake(config('safety.disk'))`
- Manipulación de URL cruzada no funciona

---

### SAF-014 · CLA-19 — Tests rutas web admin /safety/files/...

**Tipo:** Test | **Prioridad:** Media | **Depende de:** SAF-004, SAF-011

#### Tests
- pdf route redirects unauthenticated
- pdf route serves PDF for owner/super_admin
- pdf route 403 foreign
- pdf route 404 missing file
- photo route redirects unauthenticated
- photo route serves image authorized
- photo route 403 foreign

#### Criterios de aceptación
- Sin sesión → redirect/login
- `super_admin` → cualquier recurso
- `project_manager` → solo propios
- `Storage::fake(config('safety.disk'))`

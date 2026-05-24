# Sprint de Cierre — Módulo Safety

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

## Orden de ejecución aprobado

El orden respeta las dependencias de cada ticket. Los tickets sin dependencias pueden ejecutarse en paralelo dentro de la misma ola.

### Ola 1 — Sin dependencias (base)

| # | SAF | Linear | Prioridad |
|---|-----|--------|-----------|
| 1 | SAF-002 | CLA-6 | Urgente |
| 2 | SAF-001 | CLA-5 | Alta |
| 3 | SAF-008 | CLA-12 | Alta |
| 4 | SAF-011 | CLA-16 | Alta |

### Ola 2 — Dependen de Ola 1

| # | SAF | Linear | Depende de | Prioridad |
|---|-----|--------|------------|-----------|
| 5 | SAF-003 | CLA-7 | SAF-001, SAF-002 | Urgente |
| 6 | SAF-009 | CLA-13 | SAF-001, SAF-002 | Alta |
| 7 | SAF-010a | CLA-14 | SAF-001 | Media |

### Ola 3 — Dependen de Ola 1 + 2

| # | SAF | Linear | Depende de | Prioridad |
|---|-----|--------|------------|-----------|
| 8 | SAF-005 | CLA-9 | SAF-002, SAF-003 | Urgente |
| 9 | SAF-006 | CLA-10 | SAF-002, SAF-003 | Urgente |
| 10 | SAF-007 | CLA-11 | SAF-002, SAF-003 | Urgente |
| 11 | SAF-004 | CLA-8 | SAF-002, SAF-003 | Alta |
| 12 | SAF-012 | CLA-17 | SAF-011, SAF-008, SAF-009 | Alta |
| 13 | SAF-010b | CLA-15 | SAF-010a, SAF-001, SAF-002 | Baja |

### Ola 4 — Dependen de Ola 1 + 2 + 3

| # | SAF | Linear | Depende de | Prioridad |
|---|-----|--------|------------|-----------|
| 14 | SAF-013 | CLA-18 | SAF-011, SAF-005, SAF-006, SAF-007 | Alta |
| 15 | SAF-014 | CLA-19 | SAF-004, SAF-011 | Media |

---

## Reglas globales del sprint

- Disco de fotos/PDFs: `config('safety.disk')` → valor `local`
- Autorización por recurso con `Gate::authorize()`, sin cambiar el padre del controller
- `project_manager` solo ve recursos propios: `inspection.user_id === user.id`
- `super_admin` ve todos los recursos
- Tests y factories dentro de `Modules/Safety`
- No escribir código fuera de ticket aprobado

---

## Estado de tickets

| SAF | Linear | Estado |
|-----|--------|--------|
| SAF-001 | CLA-5 | Todo |
| SAF-002 | CLA-6 | Todo |
| SAF-003 | CLA-7 | Todo |
| SAF-004 | CLA-8 | Todo |
| SAF-005 | CLA-9 | Todo |
| SAF-006 | CLA-10 | Todo |
| SAF-007 | CLA-11 | Todo |
| SAF-008 | CLA-12 | Todo |
| SAF-009 | CLA-13 | Todo |
| SAF-010a | CLA-14 | Todo |
| SAF-010b | CLA-15 | Todo |
| SAF-011 | CLA-16 | Todo |
| SAF-012 | CLA-17 | Todo |
| SAF-013 | CLA-18 | Todo |
| SAF-014 | CLA-19 | Todo |

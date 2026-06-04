# Handoff — CAFCA Intelligence Hub

> Estado global vivo del proyecto. Actualizar en cada cierre de ticket.
> Última actualización: 2026-06-03 (CLA-121/122/123/124)

---

## Estado actual

- **Sprint activo:** ninguno (sesión de hotfixes)
- **Rama actual:** `main`
- **Último ticket cerrado:** CLA-124 — fix Mailing migration down() MySQL 1553 — commit `1809aee`
- **Próximo ticket:** A definir

### Hotfixes cerrados en esta sesión (2026-06-03)

| Ticket | Commit | Descripción |
|--------|--------|-------------|
| CLA-121 | `270f47c` | `User` implementa `FilamentUser` + `canAccessPanel()` — necesario para acceso al panel en producción |
| CLA-122 | `3d4c1cc` | Website: 5 factories, 5 feature tests, locale-aware API (27 tests) |
| CLA-123 | `da9ce63` | 4 migraciones raíz: down() robusto contra tabla renombrada |
| CLA-124 | `1809aee` | Mailing migration: eliminar dropIndex explícito (MySQL 1553) |

### Causa raíz login Microsoft (resuelto)

Dos problemas encadenados:
1. `User` no implementaba `FilamentUser` → Filament bloqueaba acceso en producción (CLA-121)
2. El usuario `orelvys.cuellar@claesen-verlichting.be` no existía en la tabla `users` local → callback retornaba 403 silencioso

**Fix permanente:** correr `RolesAndPermissionsSeeder` + crear usuario con rol `super_admin` al provisionar un entorno nuevo.

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
| **Website** | ✅ Sprint completado (WEB-001 a WEB-007) | `website` | `docs/website-sprint-handoff.md` |
| **Safety** | ✅ Sprint completado (SAF-001 a SAF-016) | `Safety_Inspections` | `docs/safety-sprint-linear-tickets.md` |
| **Performance** | 🚧 ~85% | `main` | Ver `CLAUDE.md` |
| **Intelligence** | 🚧 ~90% | `main` | Ver `CLAUDE.md` |
| **Prospects** | 🚧 ~75% | `main` | Ver `CLAUDE.md` |
| **Cafca** | ✅ ~90% | `main` | Ver `CLAUDE.md` |
| **Core** | ✅ ~95% | `main` | Ver `CLAUDE.md` |

---

## Bloqueantes actuales

- **MAI-026** — Webhook handler ESP externo: bloqueado por decisión de gerencia. No tocar.
- **Mailing Fase 3** (MAI-031 a MAI-036) — bloqueada hasta 4–6 semanas de datos reales en producción.
- **Backfill Website media** — `php artisan website:regenerate-media` pendiente de ejecutar en producción.

Ver `docs/ai/known-risks.md` para el detalle completo.

---

## Próximos pasos recomendados

1. **Mailing Fase 3:** esperar datos reales de campañas en producción antes de iniciar MAI-031.
2. **Website backfill:** ejecutar `php artisan website:regenerate-media` en producción.
3. **Performance:** continuar mejoras de insights y Watchdog según prioridad.
4. **Prospects:** completar CRM y campañas email (~75%).

---

## Cambios recientes

| Fecha | Ticket | Acción |
|-------|--------|--------|
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

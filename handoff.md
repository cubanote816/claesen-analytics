# Handoff — CAFCA Intelligence Hub

> Estado global vivo del proyecto. Se actualiza en cada cierre de ticket.
> **Regla de edición:** las entradas nuevas se añaden en la tabla "Cambios recientes", **al final** de la tabla — nunca se antepone texto libre arriba de este archivo. Esto preserva el prompt cache entre sesiones (ver `docs/ai/handoff-strategy.md`).
> Histórico completo previo a esta reorganización (CLA-536, 2026-09-15) → `docs/ai/handoff-archive.md` (no es lectura obligatoria).

---

## Estado actual

- **Sprint activo:** Programa Laravel 13 (CLA-514) — núcleo técnico cerrado (CLA-515→532 Done); pendiente de infraestructura real: **CLA-530** (aprovisionar staging Laravel separado de producción) y **CLA-531** (endurecer `deploy.sh`/`deploy.yml` + rollback real) bloquean **CLA-525** (In Progress) y **CLA-523** (Backlog). PR #9 (`release/laravel-13-rc1` → `main`) sigue **Draft**, sin merge, sin deploy.
- **CLA-532 = Done** (2026-09-09): eliminados los usos runtime de `env()` incompatibles con `config:cache`. CI certificada verde (`1355 passed / 0 failed / 0 errors / 2 skipped`). Detalle en `docs/ai/handoff-archive.md`.
- **CLA-536 (este ticket, In Progress):** reorganización de la documentación de IA (`CLAUDE.md`/`handoff.md`/`docs/ai/`/`.agent/skills`) para reducir el coste de tokens por sesión y restaurar el aprovechamiento de prompt cache. Ver plan y alcance en el propio ticket Linear.
- **Hallazgo central pendiente:** no existe un entorno de staging Laravel real — `deploy.yml` es un job único a `prod-priv-01`. Nada se despliega a producción hasta que CLA-530/531/525/523 cierren en ese orden.

---

## Módulos activos

| Módulo | Estado | Rama | Documento específico |
|--------|--------|------|---------------------|
| **Mailing** | ✅ Fase 0+1+2 completadas / Fase 3 en Backlog (bloqueada hasta datos reales) | `main` | `docs/Mailing/mailing-platform-master.md` |
| **Website** | ✅ Mergeado en `main` (incl. Work Details + Static Site) | `main` | `docs/website-sprint-handoff.md` |
| **Safety** | ✅ Sprint completado | `main` | `docs/safety-sprint-linear-tickets.md` |
| **Performance** | 🚧 ~85% | `main` | `docs/ai/handoff-archive.md` |
| **Intelligence / BI** | ✅ Sprint 1+2B completados | `main` | `docs/bi-sprint-plan.md` |
| **Prospects** | 🚧 CLA-535 (refactor de fuentes de federación) — 7 slices Done, docs cerrando F | `audit/prospects-module` | `docs/ai/context-map.md` |
| **Cafca** | ✅ ~90% | `main` | `docs/ai/handoff-archive.md` |
| **Core** | ✅ ~99% | `main` | `docs/ai/handoff-archive.md` |
| **FieldOps** | 🚧 batería de seguridad en curso (CLA-496/497 Done, CLA-498 pendiente de GO) | `main` | `docs/ai/handoff-archive.md` |
| **Laravel 13 (CLA-514)** | 🚧 núcleo Done, staging/deploy pendiente (CLA-530/531/525/523) | `release/laravel-13-rc1` | `docs/ai/archive/` |

---

## Bloqueantes actuales

- **CLA-530** — provisión de staging Laravel real, separado de producción. Bloquea CLA-525.
- **CLA-531** — endurecer `deploy.sh`/`deploy.yml` + rollback real probado. Bloquea CLA-525 y CLA-523.
- **MAI-026** — Webhook handler ESP externo: bloqueado por decisión de gerencia. No tocar.
- **Mailing Fase 3** (MAI-031 a MAI-036) — bloqueada hasta 4–6 semanas de datos reales en producción.
- **Backfill Website media** — `php artisan website:regenerate-media` pendiente de ejecutar en producción.

Ver `docs/ai/known-risks.md` para el detalle completo.

---

## Próximos pasos recomendados

1. Cerrar CLA-536 (esta reorganización de documentación) y confirmar con GO técnico.
2. CLA-530 — aprovisionar staging Laravel real (12 criterios, ver ticket).
3. CLA-531 — endurecer deploy + rollback ensayado en staging.
4. Con CLA-530/531 cerrados, reanudar CLA-525 (E2E por rol) y luego CLA-523.
5. Mailing Fase 3: esperar datos reales de campañas en producción.
6. Website backfill media pendiente en producción.

---

## Cambios recientes

| Fecha | Ticket | Acción |
|-------|--------|--------|
| 2026-08-30 | CLA-518/519/526/517/516/521/522/524/527/528 | Cadena completa de certificación Laravel 13 (núcleo, Spatie majors, Filament/Livewire, auth/CSRF, cache/queues/mail, PHPUnit 12, casing de migraciones, estabilización de suite). Detalle en `docs/ai/handoff-archive.md`. |
| 2026-08-30 | CLA-523 (parcial) | Fix `down()` roto de migración de luminaire positions (bloqueaba `migrate:rollback`). |
| 2026-08-31 | CLA-525 (cont.) | 1ª corrida real de CI sobre `release/laravel-13-rc1` — 103 fallos de harness eliminados, baseline diferencial certificado sin regresión de código. |
| 2026-09-01 | CLA-529 | Done — fix de zona horaria en A/B winner y follow-ups de Mailing (bug real de producción, sin relación con L13). |
| 2026-09-01 | FASE 0 | Auditoría de solo lectura del plan de despliegue Laravel 13 → producción. CLA-530/531 creados. |
| 2026-09-03 | CLA-532 | Implementación inicial — migración de `env()` a `config()` cache-safe. |
| 2026-09-09 | CLA-532 | **Done** — CI certificada verde (1355/0/0/2). PR #9 sigue Draft. |
| 2026-09-15 | CLA-536 | Auditoría del harness de IA + reorganización de `CLAUDE.md`/`handoff.md`/`docs/ai`/`.agent/skills` para reducir coste de tokens. |
| 2026-09-15 | CLA-539 | Auditoría de la config `gentle-ai` (memoria `project_gentle_ai_audit`): `rdd_mode` apagado global, effort de los 25 subagentes de `pi` diferenciado por fase, Engram desactivado (solo auto-memory nativo). En el repo: archivado el histórico ticket-por-ticket de FieldOps (CLA-266 en adelante, mismo patrón CLA-536) a `docs/ai/fieldops-decisions-log.md` — `CLAUDE.md` pasó de 245KB a 46.7KB (−81%). |
| 2026-09-15 | CLA-535 | Refactor completo de adquisición de datos de Prospects (SDD, rama `audit/prospects-module`, 7 slices A→B→D1→D2→C→E→F): arquitectura `FederationDataSource`/`NormalizedClub`/`ClubPersister` (ver `docs/ai/context-map.md`), adapters reales para RBFA/AFTT-PDF/Bruselas-CSV (nueva cobertura), fix de bugs de datos (idioma Hockey, provincias RBFA faltantes, región AFT), hardening de observabilidad (`guardedSync`, notificación de fallo de cadena maestra), y fix colateral de `region_id NOT NULL` en `LeadService`. Gaps diferidos (AFPadel/Verenigingsregister/Sport Vlaanderen) documentados en `docs/ai/known-risks.md`. Suite Prospects 118/118 (329 assertions); suite completa del repo 1446/1446 sin fallos (stack Docker aislado `claesen_api_web_oficial-*`, puertos alternos, sin tocar el otro checkout del mismo repo). Pendiente: GO técnico y commit dedicado. |

> Histórico completo de tickets anteriores (CLA-105 a CLA-517, sesiones de FieldOps/Mailing/Website/BI/Safety sin ticket formal, etc.) → `docs/ai/handoff-archive.md`.

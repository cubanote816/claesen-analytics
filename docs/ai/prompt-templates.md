# Prompt templates reutilizables — CAFCA Intelligence Hub

> Copiar y adaptar según la tarea. Siempre incluye el contexto mínimo necesario.
> Última actualización: 2026-06-02 (DOCS-AI-001 / CLA-105)

---

## Retomar sesión general

```
Continuamos con [TICKET-ID] / CLA-[N].

Lee CLAUDE.md, handoff.md y docs/ai/README.md.

El módulo activo es [Mailing|Safety|Website|Performance|...].
Lee también [docs/Mailing/mailing-platform-master.md | docs/website-sprint-handoff.md | ...].

El ticket está en In Progress en Linear (ID: CLA-[N]).
Espera mi confirmación antes de implementar.
```

---

## Retomar sesión — Módulo Mailing

```
Continuamos con MAI-[XXX] / CLA-[N].

Lee CLAUDE.md, handoff.md, docs/ai/README.md y docs/Mailing/mailing-platform-master.md.

Reglas Mailing activas:
- Transporte siempre via MarketingCampaignInterface
- mailing_message_events es append-only
- No usar aperturas como KPI — usar CTR/CTOR
- Fase 3 bloqueada hasta datos reales (MAI-031 a MAI-036)
- MAI-026 bloqueado — no tocar

El ticket MAI-[XXX] (CLA-[N]) está In Progress.
Espera mi confirmación antes de implementar.
```

---

## Retomar sesión — Módulo Safety

```
Continuamos con SAF-[XXX] / CLA-[N].

Lee CLAUDE.md, handoff.md, docs/ai/README.md y docs/safety-sprint-linear-tickets.md.

Reglas Safety activas:
- Disco local (privado), nunca public
- Gate::authorize() por recurso
- project_manager → solo sus recursos
- Tests y factories en Modules/Safety

El ticket SAF-[XXX] (CLA-[N]) está In Progress.
Espera mi confirmación antes de implementar.
```

---

## Retomar sesión — Módulo Website

```
Continuamos con WEB-[XXX] / CLA-[N].

Lee CLAUDE.md, handoff.md, docs/ai/README.md y docs/website-sprint-handoff.md.

Reglas Website activas:
- API pública en /v1/website/* sin auth
- Solo proyectos published = true
- Conversiones WebP: thumb/optimized/gallery
- NotifyAstroFrontendJob → event_type: backend_update

El ticket WEB-[XXX] (CLA-[N]) está In Progress.
Espera mi confirmación antes de implementar.
```

---

## Preparar un ticket nuevo

```
Quiero crear un ticket para: [descripción breve de la tarea].

Módulo afectado: [Mailing|Safety|Website|Performance|...]
Sprint actual: [Mailing Fase 3|Website|Performance|...]
Rama activa: [main|feature/mailing|website|...]

Antes de crear el ticket:
1. Lee CLAUDE.md y handoff.md para verificar el contexto actual.
2. Lee docs/ai/module-contracts.md para el módulo afectado.
3. Lee docs/ai/test-gate-harness.md — identifica qué tipo de cambio es y qué tests requerirá.
4. Propón título, descripción, criterios de aceptación y tests previstos.
5. Espera aprobación antes de crear el ticket o editar código.
```

---

## Revisar un PR

```
Revisa el PR [número o URL] del módulo [Mailing|Safety|Website|...].

Lee docs/ai/code-review-rubric.md y docs/ai/test-gate-harness.md antes de empezar.

El objetivo del ticket era: [descripción del objetivo].

Verifica primero el Test Gate:
- ¿Qué tipo de cambio es?
- ¿Qué tests exige test-gate-harness.md?
- ¿Qué tests se añadieron?
- ¿Se ejecutaron? ¿Resultado?

Luego prioriza en este orden:
1. Seguridad
2. Correctitud
3. Autorización
4. Idempotencia
5. Tests faltantes

Reporta cada hallazgo con formato:
[SEVERIDAD] archivo:línea — descripción
Impacto: ...
Sugerencia: ...

Al terminar: ¿hay BLOCKERs o CRITICALs? ¿Test Gate pasado?
```

---

## Cerrar un ticket

```
El ticket [TICKET-ID] / CLA-[N] está listo para cerrar.

Antes del commit, documenta el Test Gate:

### Tests añadidos
- Archivo: ... — casos: ...

### Tests ejecutados
php artisan test --filter=[Modulo]

### Resultado
PASS | FAIL — [N tests, N assertions]

### Waiver (si no hay tests automatizados)
Motivo: ...
Riesgo residual: ...
Cobertura alternativa: ...

Luego:
1. Actualiza handoff.md con el estado actual.
2. Actualiza CLAUDE.md si cambió el estado macro del módulo.
3. Confirma que no hay secretos en el diff.

Crea el commit con formato:
[TICKET-ID] / CLA-[N]: resumen corto

Muestra el hash del commit.
No marques Linear como Done hasta recibir GO técnico.
```

---

## Preparar deploy a producción

```
Vamos a hacer deploy del módulo [Mailing|Safety|Website|...].

Lee docs/ai/production-readiness.md antes de empezar.

Checklist previo:
1. ¿Tests pasan en staging?
2. ¿Hay migraciones pendientes?
3. ¿Las variables de entorno están configuradas?
4. ¿El scheduler está activo?
5. ¿Hay backfills pendientes?

Lista las acciones en orden antes de ejecutar cualquiera.
Espera confirmación antes de cada paso destructivo.
```

---

## Investigar un bug

```
Hay un bug reportado: [descripción del síntoma].

Módulo probable: [Mailing|Safety|Website|...]
Ruta o comando afectado: [URL, comando, job]
Comportamiento esperado: [qué debería pasar]
Comportamiento actual: [qué está pasando]

Antes de proponer fix:
1. Lee docs/ai/module-contracts.md para el módulo afectado.
2. Lee docs/ai/known-risks.md — ¿el bug ya estaba documentado?
3. Explora el código en modo solo lectura.
4. Propón hipótesis del root cause.
5. Propón fix mínimo y tests para verificar.

No edites código hasta que apruebe el plan.
```

---

## Validación pre-producción — Módulo Mailing

```
Valida el estado del módulo Mailing antes del deploy.

Lee docs/ai/production-readiness.md sección "Módulo Mailing".

Ejecuta en orden (solo en staging):
1. php artisan migrate:status
2. php artisan mailing:parse-bounces --dry-run
3. php artisan mailing:dispatch-scheduled --dry-run
4. php artisan mailing:ab-select-winner --dry-run
5. php artisan mailing:dispatch-followups --dry-run
6. php artisan mailing:check-deliverability-alerts --dry-run
7. php artisan test --testsuite=Modules --filter=Mailing

Reporta el resultado de cada paso.
Si alguno falla, detente y reporta el error completo.
```

---

## Contexto rápido de módulo (para nuevas sesiones)

```
Necesito contexto rápido del módulo [Mailing|Safety|Website|Performance|Cafca|...].

Lee docs/ai/context-map.md sección del módulo y docs/ai/module-contracts.md.

Responde:
- ¿Qué hace este módulo?
- ¿Cuáles son sus modelos principales?
- ¿Cuáles son sus reglas no negociables?
- ¿Qué tests existen?
- ¿Hay riesgos abiertos relevantes?
```

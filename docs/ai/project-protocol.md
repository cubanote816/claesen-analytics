# Protocolo de trabajo — CAFCA Intelligence Hub

> Flujo obligatorio para cada sesión de IA. No saltarse pasos.
> Fuente: `CLAUDE.md` sección "Flujo de trabajo con Claude"

---

## Regla maestra

**Sin ticket Linear activo no se toca código ni documentación.**

Este es el principio invariable. Cualquier cambio — por pequeño que parezca — requiere un ticket activo antes de editar cualquier archivo.

---

## Flujo por ticket (10 pasos)

```
1. MOVER issue Linear → In Progress
2. PRESENTAR plan: alcance, archivos, tests previstos
3. ESPERAR aprobación explícita (nunca asumir)
4. IMPLEMENTAR solo el ticket activo
5. EJECUTAR tests/checks relevantes
6. PRESENTAR diff + criterios de aceptación cubiertos
7. ESPERAR GO técnico del auditor
8. CREAR commit dedicado al ticket
9. MOSTRAR hash del commit
10. MARCAR issue Linear como Done con hash en comentario
```

**Pasos 3 y 7 son bloqueantes.** No hay excepción.

---

## Formato de commit

```
MAI-XXX / CLA-YY: resumen corto en inglés o español
SAF-XXX / CLA-YY: resumen corto
WEB-XXX / CLA-YY: resumen corto
DOCS-XXX / CLA-YY: resumen corto
```

- Un commit por ticket.
- No mezclar cambios de tickets distintos salvo que estén declarados y aprobados.
- El resumen describe el "qué", no el "cómo".

---

## Cambios colaterales

Si durante un ticket aparecen cambios que pertenecen a otro ticket:

1. No mezclar silenciosamente.
2. Documentar el cambio y su ticket de origen.
3. Pedir decisión explícita:
   - mover al commit/ticket correcto,
   - incluir como dependencia aprobada, o
   - revertir.

---

## Plan obligatorio antes de implementar

El plan debe incluir:

- Ticket activo (ID Linear + título)
- Rama actual
- Archivos que se van a **crear**
- Archivos que se van a **modificar**
- Archivos que se van a **eliminar** (si aplica)
- Información o lógica que va en cada archivo
- Tests previstos o checks de verificación
- Riesgos identificados
- Confirmación de que no se leerán/copiarán secretos

---

## Definition of Done (DoD)

Todo ticket debe cumplir **todos** los puntos siguientes antes de recibir GO técnico:

- [ ] **Tests automatizados** creados o actualizados según `test-gate-harness.md`
- [ ] **Tests ejecutados** y resultado documentado (comando + output)
- [ ] **Waiver explícito** documentado y aprobado si no aplican tests automatizados
- [ ] `CLAUDE.md` actualizado si cambian reglas permanentes o estado macro de módulo
- [ ] `handoff.md` actualizado con estado global actual
- [ ] Documento del módulo actualizado si cambió contexto técnico
- [ ] Commit dedicado creado
- [ ] GO técnico recibido
- [ ] Issue Linear marcado Done con hash del commit

> **Regla:** el GO técnico no puede concederse si faltan los tests requeridos por `test-gate-harness.md` y no existe waiver explícito aprobado por el auditor.

---

## Test Gate — referencia rápida

Para cualquier ticket que toque código:

1. Al **planificar**: declarar qué tests se crearán, qué fakes se usarán.
2. Al **implementar**: crear los tests junto al código.
3. Al **presentar para review**: incluir qué tests se ejecutaron y el resultado.
4. Al **cerrar**: usar la plantilla de cierre de `test-gate-harness.md`.

Ver `docs/ai/test-gate-harness.md` para la matriz completa, criterios de waiver y plantilla.

---

## Progresión de estado en CLAUDE.md

```
⬜ Todo  →  🚧 In Progress  →  ✅ Done
```

Solo actualizar CLAUDE.md cuando el estado macro del módulo cambia (sprint completo, fase cerrada, etc.), no por cada ticket.

---

## Cómo reanudar una sesión

```
"Continuamos con [TICKET-ID] / CLA-YY.
Lee CLAUDE.md, handoff.md y docs/ai/README.md."
```

Luego leer el documento específico del módulo activo según la tabla en `docs/ai/README.md`.

---

## Invariantes de seguridad (siempre)

- No leer ni copiar `.env`, `.env.*`, `storage/logs`, `vendor`, `node_modules`.
- No imprimir claves, tokens ni credenciales.
- No incluir valores reales de variables de entorno en documentación o commits.
- No hacer push automático salvo instrucción explícita.
- No marcar Done en Linear hasta recibir GO técnico.

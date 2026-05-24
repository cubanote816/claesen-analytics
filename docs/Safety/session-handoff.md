# Safety Sprint — Session Handoff

> La siguiente sesión DEBE empezar leyendo este archivo antes de cualquier acción.

---

## 1. Estado actual del sprint Safety

Rama activa: `Safety_Inspections`

| SAF | Linear | Estado | Commit |
|-----|--------|--------|--------|
| SAF-001 | CLA-5  | commit creado, Linear pendiente verificación | 7e9958d |
| SAF-002 | CLA-6  | commit creado, Linear pendiente verificación | 868ff60 |
| SAF-003 | CLA-7  | commit creado, Linear pendiente verificación | 3bf5408 |
| SAF-004 | CLA-8  | ⬜ Todo — no iniciado | — |
| SAF-005 | CLA-9  | commit creado, Linear pendiente verificación | a9638dc |
| SAF-006 | CLA-10 | commit creado, Linear pendiente verificación | b0a7f40 |
| SAF-007 | CLA-11 | ⬜ Todo — bloqueado hasta verificación SAF-006 | — |
| SAF-008 | CLA-12 | commit creado, Linear pendiente verificación | 4556064 |
| SAF-009 | CLA-13 | commit creado, Linear pendiente verificación | e28ef5f |
| SAF-010a | CLA-14 | commit creado, Linear pendiente verificación | 824c4aa |
| SAF-010b | CLA-15 | ⬜ Todo — no iniciado | — |
| SAF-011 | CLA-16 | commit creado, Linear pendiente verificación | 0ada386 |
| SAF-012 | CLA-17 | ⬜ Todo — no iniciado | — |
| SAF-013 | CLA-18 | ⬜ Todo — no iniciado | — |
| SAF-014 | CLA-19 | ⬜ Todo — no iniciado | — |
| SAF-015 | CLA-50 | commit creado, Linear pendiente verificación | c1ed9fa |
| SAF-016 | CLA-51 | commit creado, Linear pendiente verificación | dad5d70 |

---

## 2. Regla de trabajo vigente

- **No marcar Linear Done sin GO técnico del auditor.**
- Antes de marcar Done debe existir un commit dedicado con formato `SAF-XXX / CLA-YY: resumen corto`.
- Después del commit debe ejecutarse verificación post-commit y pasar sin errores.
- No implementar el siguiente ticket sin confirmación explícita de cierre del anterior.
- Cambios colaterales fuera del ticket activo no se mezclan silenciosamente: se documentan y se pide decisión.

---

## 3. Commits ya creados en esta sesión

```
7e9958d  SAF-001 / CLA-5:  base Safety module config
868ff60  SAF-002 / CLA-6:  InspectionPolicy resource authorization
3bf5408  SAF-003 / CLA-7:  switch storage disk to local private
c1ed9fa  SAF-015 / CLA-50: add incident checklist support
dad5d70  SAF-016 / CLA-51: add project list fallback resilience
4556064  SAF-008 / CLA-12: extract StoreInspectionRequest validation
e28ef5f  SAF-009 / CLA-13: index() pagination, filters, and super_admin scope
824c4aa  SAF-010a / CLA-14: ComplianceService and compliance command refactor
0ada386  SAF-011 / CLA-16: HasFactory on Safety models, factories, and Modules test suite
a9638dc  SAF-005 / CLA-9:  GET inspections/{id} show endpoint
b0a7f40  SAF-006 / CLA-10: GET inspections/{id}/pdf download with stream safety
8103473  docs: Safety sprint planning and Linear tickets
e615f3f  chore: add sprint workflow and commit rules to CLAUDE.md
```

---

## 4. Estado de Linear

- **No marcar Done todavía.** Todos los issues están en Todo o In Progress.
- Pendiente verificación post-commit antes de cerrar cualquier issue.
- Issues que **podrán** cerrarse solo si la verificación pasa:
  - CLA-5, CLA-6, CLA-7, CLA-9, CLA-10, CLA-12, CLA-13, CLA-14, CLA-16, CLA-50, CLA-51
- **No cerrar** CLA-17, CLA-18, CLA-19 — SAF-012, SAF-013 y SAF-014 no están implementados.

---

## 5. Verificación post-commit pendiente

Ejecutar exactamente en este orden:

```bash
./vendor/bin/sail artisan test Modules/Safety/tests/Feature/InspectionShowTest.php --no-coverage
./vendor/bin/sail artisan test Modules/Safety/tests/Feature/InspectionDownloadPdfTest.php --no-coverage
php artisan route:list --path=api/v1/safety/inspections
git status --short
git log --oneline -13
```

Todos los tests deben pasar (5/5 en cada suite). Si alguno falla, reportar bloqueo y no avanzar.

---

## 6. Archivos excluidos — deben seguir sin commitear

```
config/media-library.php
Modules/Website/Models/Project.php
.agent/skills
scratch/
storage/media-library/temp/
.claude/
```

---

## 7. Próximo paso de la siguiente sesión

1. Leer este archivo (`docs/Safety/session-handoff.md`).
2. Ejecutar la verificación post-commit del punto 5.
3. Si pasa: pedir GO al auditor para marcar Linear Done con los hashes del punto 3.
4. Si falla: reportar el bloqueo exacto y no avanzar.
5. Solo después de cerrar los issues pendientes: continuar con SAF-007 / CLA-11.

---

## 8. Prohibido en la próxima sesión

- Reconstruir tickets desde memoria sin leer este archivo y `CLAUDE.md`.
- Marcar cualquier issue Done sin haber ejecutado la verificación del punto 5.
- Implementar SAF-007 antes de verificar y cerrar el estado actual.
- Crear commits sin GO técnico previo.
- Mezclar cambios de varios tickets en un solo commit sin declaración explícita.

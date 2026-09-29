# CLA-611 (parte) — La pantalla que aprueba páginas e idiomas

- **Rama:** `electrobertels/i18n-publication` (apilada sobre `electrobertels/site-content-api`). Worktree: `/home/totti/claesen/electrobertels-i18n-publication`
- **Linear:** CLA-611
- **Objetivo:** que el estado `(página, idioma)` de `website_page_publications` tenga **escritor**. Hoy el endpoint de mi slice anterior sólo **lee** ✗: nadie puede mover `machine → reviewed → published`, así que el manifiesto nunca cambia por decisión de una persona.
- **Estado:** ✅ implementado y verificado (2026-09-29).

## Decisiones del usuario (2026-09-29)

1. **El cliente que aprueba es el responsable, automáticamente.** Al aprobar queda registrado su usuario. Sin roles nuevos, sin aprobador intermedio.
2. **Las filas las crea el seeder** (no la pantalla): todos los pares `(página, idioma)` existen para poder aprobarlos.
3. **El cliente puede retirar** lo que publicó (vuelve a no indexable), no sólo avanzar.
4. **El panel se abre a los usuarios del cliente** → va en el **slice 2**, no aquí: toca el ADR D10 y la frontera de acceso (ver abajo).

## Discrepancia declarada

La opción elegida decía "8 páginas × 4 idiomas" ✗. Son **7 páginas de contenido** × 4 = **28 filas** ✗: las **3 páginas legales** quedan **fuera** ✔, porque su gate es su propio `status: draft` en el contenido (decisión ya tomada al diseñar el manifiesto). No invento una fila legal para llegar a 32 ✗.

## Por qué el slice 2 no se mezcla con éste

`Modules/Core/Models/User.php::canAccessPanel()` deja el panel `bertels` en `super_admin` ✗, y el **admin** admite a cualquier usuario activo ✔ — esa rama sólo está frenada por `config('organizations.enforce')`, **apagado hoy** ✗. El ADR D10 lo dice sin rodeos: *"hasta que P5 esté completa y verificada no debe existir ningún usuario real de Electro Bertels, porque los roles globales le darían acceso a datos de Claesen"*. Abrir el panel es, por tanto, una **frontera de seguridad** ✗: requiere regla por organización **y** cerrar el admin a organizaciones ajenas **y** enmendar el ADR. Meter eso dentro de una pantalla de revisión sería esconder un cambio de acceso en un commit de UI ✗.

## Tareas

- [x] **P1** Seeder: materializar los 28 pares (7 páginas × los idiomas del sitio). `nl` queda `published` (la verdad de hoy, ya lo hacía); el resto entra como `machine` — existe como borrador y **no** se indexa nunca (el manifiesto sólo publica `published`), que es exactamente lo correcto mientras esos idiomas no tengan ni slug ni copy aprobado
- [x] **P2** `PagePublicationReviewPage` en `app/Filament/Clusters/Website/Pages/`, espejando `TranslationReviewPage`: tabla scopeada al sitio del panel, filtro por estado
- [x] **P3** Acciones **Aprobar** (`reviewed`) y **Publicar** (`published`), con confirmación, y **Retirar** (vuelve a no indexable). Sin "editar" ni "retraducir" ✗: el copy de las páginas vive en Git (D-F), así que aquí no hay valor que editar
- [x] **P4** El responsable se estampa **solo**: `reviewed_by_user_id` + `reviewed_at` desde el usuario que actúa, y `Activity::causedBy(auth()->user())` como ya hace la pantalla existente
- [x] **P5** Claves i18n nuevas en los dos idiomas del repo
- [x] **P6** Tests: transición feliz, retirada, responsable estampado, y que **no** se vean filas de otro sitio
- [x] **P7** Verificar (suite del módulo + la página en un test Filament) y commitear

## Decisiones de este slice (declaradas)

- **Retirar devuelve a `reviewed`**, no a `machine`: el efecto es idéntico (no indexable) y conserva el registro de que esa persona ya lo había aprobado. Si se prefiere "vuelve a borrador puro", es cambiar una constante.
- **La acción `published` exige pasar por `reviewed`** (como en la pantalla existente): aprobar y publicar son dos actos deliberados, nunca uno implícito.
- **No se toca `canAccessPanel`** en este slice: la pantalla queda accesible para quien ya puede entrar al panel hoy (`super_admin`) y así es verificable sin abrir ninguna frontera.

## Fuera de alcance

- Abrir el panel a los usuarios del cliente (slice 2, con su propio GO): `canAccessPanel`, el cierre del panel admin a organizaciones ajenas y la enmienda del ADR D10.
- El sync del sitio: ya está hecho (`electro-bertels-official`, `46ba91b` + `e1ad921`).
- La aprobación del contenido **dinámico**: ya existe (`TranslationReviewPage`), con actor auditado.

## Verificado

| Chequeo | Resultado |
| --- | --- |
| Tests de la pantalla (`PagePublicationReviewPageTest`) | **4/4**, 22 aserciones: aislamiento por sitio, responsable estampado + auditado, publicar sólo tras aprobar, y **retirar cambia el manifiesto** |
| Tests del seeder | **6/6**, 10 aserciones (28 pares, sólo el idioma por defecto publicado, idempotente, sin autor) |
| Módulo `Modules/Website` | **248 passed** (761 aserciones) |
| Baseline | **78 passed** (380 aserciones) con los snapshots regenerados: `422 → 424` rutas, **+2** (una por panel), puramente aditivo |
| Registro de paneles | la página aparece en **los dos** paneles, porque el clúster Website se descubre en ambos; `canAccess()` la limita al panel cuyo sitio existe |

## Hallazgos declarados

- **`activity('nombre')` es el helper correcto, no `Activity::causedBy()` + `->log('nombre')`**: en `spatie/laravel-activitylog` v5 el nombre de log es el **argumento del helper**; pasarlo a `log()` (como hace la pantalla existente, `TranslationReviewPage`) archiva la entrada bajo `default`. No es un fallo de auditoría — el `causer` se guarda igual — pero **no se puede filtrar por evento**. **No toqué ese fichero** (pertenece a la rama de la otra sesión): lo dejo reportado. Aquí se usa `useLogName`-equivalente vía `activity('page_publication_review')`.
- La tabla de la pantalla usa la relación **existente** `reviewedBy` del modelo: mi primer intento añadió un `reviewer()` duplicado y se retiró.
- El baseline tiene una lista **inline** en `PanelRegistrySnapshotTest` que `UPDATE_BASELINE_SNAPSHOTS=1` **no** regenera: hay que añadir la página a mano y en orden.

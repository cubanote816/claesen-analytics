# CLA-611 (parte) — Abrir el panel `bertels` a los usuarios del cliente

- **Rama:** `electrobertels/i18n-publication` (apilada sobre `electrobertels/site-content-api`). Worktree: `/home/totti/claesen/electrobertels-i18n-publication`
- **Linear:** CLA-611 (decisión del usuario, 2026-09-29: "abrir el panel a los usuarios del cliente")
- **Objetivo:** que una persona del cliente pueda entrar al panel `bertels` y aprobar sus páginas. Hoy `canAccessPanel()` lo deja en `super_admin` y **el cliente no puede llegar a la pantalla** que ya existe.
- **Estado:** ✅ implementado y verificado (2026-09-29).

## Por qué esto no es "quitar una condición"

El ADR D10 dice: *"hasta que P5 esté completa y verificada no debe existir ningún usuario real de Electro Bertels, porque los roles globales le darían acceso a datos de Claesen"*. El código lo confirma y localiza el peligro:

```php
// bertels: sólo super_admin  ← demasiado estrecho
// admin:    admite a CUALQUIER usuario activo ← sólo lo frena el flag
if (config('organizations.enforce') && $this->organization_id !== Organization::claesenId()) {
    return false;
}
```

El flag `organizations.enforce` está **apagado** hoy, así que la única barrera real entre un usuario de Bertels y los datos de Claesen es que **hoy no existe ninguno**. Abrir el panel sin cerrar esa puerta sería exactamente lo que el ADR prohíbe.

## Frontera que este slice debe dejar cerrada

| Quién | Panel `bertels` | Panel `admin` |
| --- | --- | --- |
| Usuario de la organización dueña del panel (Bertels) | ✅ entra | ❌ **nunca** |
| `super_admin` | ✅ (como hoy) | ✅ (como hoy) |
| Usuario de Claesen | ❌ (como hoy) | ✅ (como hoy) |
| Sin organización / inactivo | ❌ | ❌ |

## Tareas

- [x] **P1** Medir antes de decidir: ¿cuántos usuarios tienen `organization_id` nulo? ¿los `super_admin` lo tienen? ¿qué hace hoy el login del panel con un usuario de otra organización?
- [x] **P2** `canAccessPanel()`: en `bertels`, admitir a los usuarios **de la organización dueña del sitio del panel** (más `super_admin`, como hoy). Fail-closed: sin sitio, sin organización o inactivo → fuera
- [x] **P3** `canAccessPanel()`: en `admin`, **rechazar incondicionalmente** a un usuario cuya organización no sea la de Claesen (hoy sólo lo evita el flag apagado). Declarar la desviación del diseño D4 y por qué: la admisión a un panel es una frontera de acceso, no una regla de negocio que se pueda dejar apagada
- [x] **P4** Tests de la matriz de arriba, incluida la regresión de que un usuario de Claesen sigue entrando a `admin` y que **un usuario de Bertels no entra a `admin`**
- [x] **P5** Enmienda del ADR D10 (y `docs/ai/known-risks.md` si corresponde): qué cambia, qué sigue pendiente (P5/P7) y qué NO se ha abierto
- [x] **P6** Verificar (tests + baseline de paneles) y commitear

## Riesgos declarados

- **Toca autenticación**, no una pantalla. Cualquier usuario real que hoy entre por un camino no previsto se quedaría fuera; los tests de la matriz existen para que eso se vea antes de mergear, no después.
- **`organization_id` nulo** es el caso peligroso: si `super_admin` lo tiene nulo, "rechazar a quien no sea de Claesen" los echaría fuera. Se mide en P1 antes de escribir la regla.
- No se toca todavía: el alta real del sitio/organización de Bertels (paso manual) ni P5/P7 del programa.

## Cómo quedó (y qué se midió antes de decidirlo)

`users.organization_id` es **nullable** y `DatabaseSeeder` **no lo asigna**: los
`super_admin` sembrados lo tienen **nulo**. Si la regla del admin se hubiera escrito
como "rechazar a quien no sea de Claesen" a secas, habría dejado fuera al equipo actual.
Por eso la regla es: **rechazar cuando la organización está definida y no es la de
Claesen**, y `super_admin` entra a los dos paneles (como hoy). El repo trae
`core:backfill-user-organizations` (F1/P2) para cerrar el caso nulo, que queda declarado.

| Quién | `bertels` | `admin` |
| --- | --- | --- |
| Usuario de la organización del sitio del panel | ✅ | ❌ |
| `super_admin` (aunque tenga organización nula) | ✅ | ✅ |
| Usuario de Claesen | ❌ | ✅ |
| Organización nula, sin `super_admin` | ❌ | ✅ (residual declarado) |
| Inactivo | ❌ | ❌ |

## Verificado

| Chequeo | Resultado |
| --- | --- |
| Matriz (`CanAccessPanelMatrixTest`) | **7/7** (12 aserciones), incluida la fila clave: un usuario de Bertels entra a `bertels` y **no** a `admin`, con el flag **apagado** y encendido |
| `Modules/Core` + baseline | **372 passed, 0 failed** (1178 aserciones) |

## Hallazgo: dos tests fijaban el agujero por escrito

`PanelOrganizationEnforcementTest` tenía dos tests que afirmaban, con el flag apagado,
que un usuario de **otra** organización *podía* usar el panel `admin` (uno de ellos
literalmente "una URL manipulada **todavía** entra"). No eran un descuido: documentaban
el diseño D4. Al cerrar la frontera **fallan**, y se han **reescrito y renombrado** para
documentar la frontera en vez del hueco, con el docblock de la clase explicando la
desviación. No se ha borrado ninguno.

Los otros dos fallos de la primera corrida eran **del entorno**, no del cambio: faltaba
`public/build/manifest.json` en este worktree (`ViteManifestNotFoundException`), el mismo
artefacto que ya mordió antes. Resuelto construyendo los assets; esos tests pasan.

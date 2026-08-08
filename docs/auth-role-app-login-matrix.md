# Login: qué rol entra a qué app

> Estado: implementado y testeado (CLA-363, 2026-08-08). Commiteado en los 3 repos involucrados, **no pusheado a origin todavía**. Fuente de verdad — si el código contradice esto, el código está mal, no este doc.

## Matriz

| Rol | Client Portal | Safety | Sport | Backoffice (Filament) |
|---|---|---|---|---|
| `client` | ✅ único acceso | ❌ | ❌ | ❌ |
| `technician` | ❌ | ❌ | ✅ único acceso | ❌ |
| `project_manager` | ❌ | ✅ | ✅ | ✅ login, pero sin ninguna sección asignada todavía (gap de permisos Filament, no de login — fuera de alcance) |
| `super_admin` / `admin` | ❌ | ✅ | ✅ | ✅ |
| `financial_manager` / `hr_manager` / `viewer` | ❌ | ❌ | ❌ | ✅ (acceso preexistente, sin cambios) |

Cualquier rol no listado, o un usuario sin rol, queda rechazado en las 4 apps.

## Dónde vive cada gate

| App | Mecanismo | Archivo |
|---|---|---|
| Client Portal | `AuthController::loginClientPortal()` → `attemptSessionLogin(..., ['client'])` | `Modules/Core/Http/Controllers/Auth/AuthController.php` |
| Sport | `AuthController::loginSport()` → `attemptSessionLogin(..., ['technician','project_manager','super_admin','admin'])` | idem, ruta `POST /api/v1/auth/login/sport` en `Modules/Core/routes/web.php` |
| Safety (password) | **Su propio controller**, no pasa por Core | `Modules/Safety/Http/Controllers/AuthController::login()`, ruta `POST /api/v1/login`. Ya existía antes de CLA-363 — hace `Auth::login()` + rechaza con 403 si no es `project_manager`/`super_admin`/`admin`. |
| Login con Microsoft (las 4 apps) | `MicrosoftAuthController::callback()`, chequea rol **antes** de `Auth::login()` | `Modules/Core/Http/Controllers/Auth/MicrosoftAuthController.php` — helper `roleGateForFrontend()` para client-portal/safety/sport; chequeo aparte para `source === 'filament'` (deny-list `client`+`technician`) |
| Backoffice (password nativo Filament) | Página de login propia, no `User::canAccessPanel()` | `Modules/Core/Filament/Pages/Auth/Login.php`, wireado en `app/Providers/Filament/AdminPanelProvider.php` vía `->login(Login::class)` |

### Por qué el Backoffice usa una página de login a medida en vez de `canAccessPanel()`

`User::canAccessPanel()` no es solo el gate del login — Filament también lo evalúa en **cada request** al panel (middleware `Authenticate`). Si se bloquea ahí, una sesión ya existente de un usuario `client`/`technician` recibe un 403 crudo en *cualquier* ruta del panel, incluida la de logout (que vive dentro del mismo grupo de middleware). Eso deja a alguien con sesión vieja sin forma de salir desde la UI.

La solución: `Modules/Core/Filament/Pages/Auth/Login.php` extiende la página de login de Filament y sobreescribe `authenticate()` para agregar el rechazo de `client`/`technician` **solo dentro del cierre de `attemptWhen()`**, que solo corre en el momento del login. `canAccessPanel()` queda como estaba (solo chequea `is_active`), y `hasPanelAccess()`/`EnsurePanelAccess` (que ya bloqueaban a `client` de todo recurso real, desde antes de este ticket) siguen gobernando las sesiones ya existentes sin cambios.

Es una duplicación parcial del método `authenticate()` de Filament (no hay un punto de extensión más chico para inyectar una regla extra en ese cierre) — si Filament cambia esa clase en un upgrade, este archivo necesita re-diffearse contra la versión nueva.

## Config nueva

`Modules/FieldOps/Config/config.php` → `safety_app_url` (env `SAFETY_URL`), sin default local a propósito. Se usa solo para que `MicrosoftAuthController::roleGateForFrontend()` reconozca cuándo el destino del login por Microsoft es Safety. En local, mientras `SAFETY_URL` no esté seteada, ese gate específico queda inerte (no rompe nada, simplemente no aplica).

## Frontends migrados

- `Claesen-Sport-updateing/src/services/auth.service.ts` — `login/spa` → `login/sport`.
- `Claesen-Sport/src/components/Login.tsx` (legacy) — `login/spa` → `login/sport`.
- `Claesen-Safety` — **sin cambios**, ya usaba su propio endpoint correcto (`/api/v1/login`).
- `Claesen-Client` (Client Portal) — sin cambios, ya usaba `login/client-portal` desde CLA-344.

## Pendiente

- CLA-364 — qué ve cada rol *dentro* de FieldOps una vez logueado (scoping de datos, no de login). Bloqueado por este ticket, sin empezar.
- Nada de esto está pusheado a `origin` en ninguno de los 3 repos todavía.

## Historial

- `login/spa` (endpoint viejo sin restricción de rol, usado antes por Safety/Sport) se eliminó una vez confirmado que ningún frontend lo llamaba más — ver `git log -- Modules/Core/Http/Controllers/Auth/AuthController.php`.

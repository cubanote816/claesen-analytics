# CLA-603 — Login mutiempresa: el mockup, adaptado a cada empresa

- **Rama:** `electrobertels/login-per-company` (sobre el tip del stack `electrobertels/knx-entry-point`). Sin push.
- **Worktree:** `/home/totti/claesen/electrobertels-login`
- **Linear:** CLA-603 (In Progress)
- **Objetivo:** el login del backoffice se ve exactamente como el mockup aprobado (`diseno/Rediseño de login profesional backoffice/Login.dc.html`), en **ambos** paneles, adaptado a cada empresa (logo y color de marca propios e incluido el flujo de contraseña: forgot/sent/reset/done).

## Decisiones del usuario (2026-09-26)

1. El panel de marca muestra **solo el logo de la empresa** del panel.
2. Color de acento = **color de marca de cada empresa**.
3. Alcance: login **+ forgot/reset**.
4. Baseline: partir del WIP de CLA-601 (sin commitear en `main`) preservándolo.
5. Rama nueva sobre el tip del stack (único sitio con el panel `bertels` y `Site`/`Organization`).

## Punto de partida y por qué

`main` tiene CLA-601 (login del mockup, admin) pero **no** tiene `BertelsPanelProvider`, `Site` ni `Organization`. El stack `electrobertels/*` tiene ambas cosas pero **no** tiene CLA-601. Las dos líneas eran disjuntas, así que el trabajo se hace sobre el stack y el WIP de `main` se porta como baseline (`ae5d632`). `main` **no se tocó**.

## Hallazgo que originó el ticket

En `/bertels/login` el logo de Microsoft medía **416 × 416 px** (botón de 434 px de alto) frente a 20 × 20 en `/login`. Causa doble:

1. `BertelsPanelProvider` no tiene `->viteTheme(...)` (solo el admin), así que ninguna utilidad Tailwind del proyecto (`h-5 w-5`, `ring-1`, `shadow-sm`…) se compila en ese panel.
2. El CSS del mockup (`.cafca-login-*`) vivía **dentro** de `brand-panel.blade.php`, registrado solo para `filament.admin.auth.login`, mientras que `microsoft-login-button` se registra **global** (`AUTH_LOGIN_FORM_AFTER`) → en Bertels se pintaba markup con clases inexistentes.

## Qué se hizo

### S1 — Baseline (`ae5d632`)
Portado el WIP de CLA-601: `Login.php`, `lang/{en,nl}/auth.php`, `brand-panel.blade.php`, `microsoft-login-button.blade.php` y `public/img/claesen-logo-login.png`. Verificado archivo a archivo contra el working tree de `main`.

### S2 — CSS agnóstico de panel (`d433f16`)
- Todo el CSS sale a `Modules/Core/resources/views/filament/auth/login-theme.blade.php`; `brand-panel.blade.php` queda solo con el markup.
- Los hooks de auth se registran para `filament.*.auth.*` de **todos** los paneles: brand column (`SIMPLE_LAYOUT_START`), theme (`HEAD_END`) y el forzado de modo claro (`HEAD_END`, con el MutationObserver de CLA-601). Ya no dependen del theme Vite del admin.
- Sin sitio resuelto **no se pinta nada** (nunca cae al logo de la otra empresa); `.fi-simple-main-ctn` es `flex:1 1 340px`, así que el formulario queda centrado y a ancho completo.

### S3 — Por empresa (`d433f16`)
- `config/organizations.php` → `login_brand` por sitio: `accent_hue`, `logo`, `logo_alt`, `logo_height`.
- `Site::loginBrand()` es la única resolución; el hue es el que Filament ya deriva del `->colors()['primary']` de cada panel (Claesen `#00aeef` → **234.363**; Electro Bertels `#EE7203` → **50.626**), con la lightness/chroma del mockup (0.55 / 0.16).
- **Eliminado** el ramp `--primary-*` hardcodeado a hue 255: Filament ya genera la paleta por panel, así que el acento no está duplicado en dos sitios.
- Viñetas alineadas a la primera línea (`align-items: flex-start`), porque el copy real ocupa dos líneas.

### S4 — Forgot/reset (`a147c3c`)
- `Modules/Core/Services/PasswordResetService.php`: la lógica (código de activación hasheado, caducidad 60 min, **solo cuentas locales** `microsoft_id === null`, activas y con setup completado, anti-enumeración, revocación de tokens) sale de `PasswordResetController` para que la API/PWAs y el backoffice no puedan divergir.
- `PasswordResetNotification` acepta una URL explícita; sin ella mantiene el enlace al client portal (CLA-371) → la API no cambia.
- `Modules/Core/Filament/Pages/Auth/{RequestPasswordReset,ResetPassword}.php` extienden las páginas de Filament (así heredan el simple-layout y el brand column) pero `request()`/`resetPassword()` delegan en el servicio, **no** en el broker `password_reset_tokens`.
- Ambos paneles registran `->passwordReset(...)`; el login muestra "Wachtwoord vergeten?" con nuestra etiqueta.
- Pantallas del mockup: forgot, **sent**, reset (con checklist en vivo) y **done**.
- Snapshots `ClaesenBaseline` regenerados: solo las 4 rutas nuevas (`total_routes` 412 → 416).

## Desviaciones deliberadas del mockup (y por qué)

1. **La pantalla "sent" no tiene el botón "Open reset link"** del mockup. Ese enlace solo existiría para cuentas que existen, que es exactamente la enumeración que el flujo evita (la API responde genéricamente por lo mismo). Se mantienen "resend email" y "back to sign in".
2. **El reset no redirige al login al terminar**: el mockup acaba en una pantalla "Password updated" con botón "Continue to sign in", así que la página se queda ahí (`$done`).
3. **No se muestra el campo email (deshabilitado)** que Filament pone por defecto en la pantalla de reset: el mockup tiene solo los dos campos de contraseña.

## Evidencia

- Suite completa: pendiente (ver abajo).
- `Modules/Core/tests/Feature/BackofficePasswordResetTest.php` — **8/8**, 32 aserciones (rutas de ambos paneles, brand column propio, enlace del email apuntando al panel correcto, mismo trato para email desconocido, cuenta Microsoft excluida, reset feliz, código caducado, URL sin firma → 403).
- `Modules/Core/tests` + `tests/Feature/ClaesenBaseline` — **374 verde** antes de regenerar snapshots.
- `PasswordResetFlowTest` + `PasswordSetupFlowTest` (API existente) — **23/23** tras extraer el servicio.
- Playwright (1440×900) con el stack aislado: icono Microsoft **14 × 14** y botón **46 px** en ambos paneles (antes 416 px en bertels); acento `oklch(0.55 0.16 234.363)` (cyan) en admin y `oklch(0.55 0.16 50.626)` (naranja) en bertels; logo correcto por panel; forgot/reset/done con el diseño del mockup.

## Arnés para reproducir (condiciones del entorno, no del branch)

- `public/build` debe existir (los tests de paneles Filament compilan el theme).
- `APP_LOCALE=en` (los snapshots `ClaesenBaseline` capturan las etiquetas de navegación en inglés; el `.env` de `main` es `nl`).
- `MICROSOFT_GRAPH_CLIENT_ID/TENANT_ID/CLIENT_SECRET` sin definir (= intención de CI).
- La BD de test (`claesen_analytics_web_testing`) debe existir: el `create-testing-database.sh` de Sail solo crea `testing`, así que hay que crearla a mano en stacks nuevos.

## Fuera de alcance

- Cambiar el mecanismo de reset en sí (sigue siendo el de CLA-371).
- Producción / push.
- Guardo Azure (`microsoft_id !== null`) fuera del reset local: es deliberado y se mantiene.

## Siguiente paso

Revisión del diff, push de la pila y PR contra `electrobertels/trunk-merge-main` (requiere autorización explícita). Pendiente de producto: decisión sobre MFA de Bertels (ADR D8) y alta de usuarios de Bertels.

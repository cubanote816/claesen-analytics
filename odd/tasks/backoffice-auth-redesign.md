# Backoffice auth redesign — Sign in, Forgot password, Reset password

Branch `electrobertels/trunk` (user decision, 2026-10-23: trunk already has the panel password-reset pages, MFA and the `/bertels` panel; `main` has none of that). UI-only change on top of the existing Filament 5 auth pages.
Visual source of truth: `odd/tasks/backoffice-auth-redesign.reference.html` (the user's `backoffice-auth-design-reference.html` with its 6 base64 PNGs stripped; otherwise byte-identical). Ignore `.ref-bar`, the demo `<script>` and the `#signin/#forgot/#reset` hashes.

## Specs

**S1 — Stack and reuse.** "Detecta el stack del proyecto (framework, motor de vistas/componentes, CSS, auth actual: Laravel Breeze/Fortify/Jetstream/custom, Socialite para Microsoft, etc.) y reutiliza lo que ya existe. No metas dependencias nuevas si no hacen falta." / "Localiza las vistas/rutas/controladores actuales de login, forgot y reset password y modifícalos en lugar de crear duplicados." / "El HTML es la fuente de verdad visual. Respeta tal cual tokens (variables CSS de `:root`), tipografías (Sora + Inter), espacios, radios, tamaños, textos en inglés y estados (error de credenciales, "Check your inbox", "Password updated", error de contraseñas que no coinciden). Trasládalo a las convenciones de nuestro proyecto (componentes, Tailwind/CSS, Blade/Vue/React, etc.); no copies el HTML en bruto." / "IGNORA lo que es solo de demo: la barra `.ref-bar`, el `<script>` de demostración y los atajos `#signin/#forgot/#reset`. Usa las rutas reales de la app."

**S2 — Auth logic untouched.** "Mantén intacta la lógica de autenticación existente (validación, throttling, "remember me", flujo de Microsoft SSO, tokens de reset). Esto es un cambio de UI; solo toca backend si algo del diseño lo exige y avísame antes."

**S3 — Shared layout.** "Pantalla partida en dos paneles, `min-height: 100vh`, que en móvil (<~1000px) se apilan (marca arriba, formulario abajo)."
"**Panel izquierdo (marca)**, fondo `#07111E`, texto blanco, padding `clamp(32px, 5vw, 72px)`, contenido en columna con `justify-content: space-between`:
- Fondo: rejilla de líneas de 1px `rgba(255,255,255,.035)` cada 48px + resplandor `radial-gradient(60% 50% at 100% 0%, rgba(34,118,210,.30), transparent 70%)` + 4 círculos concéntricos decorativos en SVG (stroke blanco al 4-7%) abajo a la derecha, `aria-hidden`.
- Arriba: logos de Claesen y Electro Bertels (altura 52px, `alt` descriptivo) separados por una línea vertical de 1px `rgba(255,255,255,.2)` y 36px de alto. Usa los archivos de logo que ya existan en el proyecto; si no, pídemelos (versión blanca/transparente, preferible SVG).
- Centro: `h1` "Two trades, one shared backoffice" (Sora 600, `clamp(34px, 4vw, 52px)`, line-height 1.08, letter-spacing -0.02em, `text-wrap: balance`), debajo el párrafo "Sports lighting and electrical installations — one shared backoffice for both companies." (17px, `#C3CEDF`).
- Debajo, dos tarjetas (radius 14, fondo `rgba(255,255,255,.045)`, borde `1px rgba(255,255,255,.10)`, padding 18/20) con un punto de 10px de color de marca + título (Sora 600, 16px) + descripción (15px, `#C3CEDF`):
  - Punto `#2FD3E6` — "Claesen" — "Outdoor & stadium sports lighting, every level"
  - Punto `#FF7A1A` — "Electro Bertels" — "Electrical installations & home automation for homes, businesses and industry"
- IMPORTANTE: NO debe aparecer en ningún sitio el texto "Cash-flow guardian & AI auditor for every project".
- Abajo: "© 2026 · All rights reserved" (13px, `#9FB0C7`). Usa el año actual dinámico si el proyecto ya lo hace."
"**Panel derecho (formulario)**, fondo `#F6F8FB`, formulario centrado vertical y horizontalmente, `max-width: 420px`, gap 28px entre bloques."

**S4 — Tokens.** "Fuentes (Google Fonts): Sora 500/600/700 para títulos, Inter 400/500/600 para el resto.
- Azul primario `#1B66C0` (hover `#155AAD`), texto fuerte `#0B1727`, texto secundario `#4A586B`, borde de inputs `#C5CEDB`, divisor `#D3DAE5`, error texto `#8F1236` sobre `#FDEDF1` con borde `#F3B7C6`, ring de foco `3px solid #7DB3F2` con offset 2px.
- Inputs y botones: alto 52-54px, radius 12px, fuente 16px (evita el zoom en iOS). Los controles interactivos miden mínimo 44px.
- Todos los pares texto/fondo deben cumplir contraste 4.5:1."

**S5 — Sign in.** "- `h2` "Sign in" (Sora 600, 34px) + "Welcome back. Enter your details to continue." (16px).
- Mensaje de error de credenciales como aviso encima de los campos (`role="alert"`, icono de círculo con exclamación, texto "These credentials do not match our records."), no pegado bajo el email. Debe mostrarse con el error de validación real de auth.
- Campo "Email address *" (`type=email`, `autocomplete="username"`, placeholder "you@company.com").
- Campo "Password *" con botón "SHOW/HIDE" dentro del input (alterna `type` password/text; `aria-label` "Show password"/"Hide password"; 44px de alto, mayúsculas 12px).
- Fila: checkbox "Remember me for 30 days" a la izquierda y enlace "Forgot password?" (azul, 600) a la derecha, con `flex-wrap` para que en pantallas estrechas baje de línea.
- Botón primario "Sign in" (ancho completo, 54px).
- Divisor "OR" (líneas a los lados).
- Botón secundario blanco con borde "Sign in with Microsoft" con el logo de 4 cuadrados (#F25022, #7FBA00, #00A4EF, #FFB900), conectado al flujo SSO existente."

**S6 — Forgot password.** "- Enlace arriba "‹ Back to sign in" (vuelve al login).
- `h2` "Forgot your password?" + "Enter the email linked to your account and we'll send you a link to reset it."
- Campo "Email address *" + botón primario "Send reset link".
- Tras enviar, estado de confirmación (`role="status"`): icono de sobre en un cuadrado redondeado `#E3EEFB`, `h2` "Check your inbox", texto "If an account exists for **{email}**, you'll receive a link to reset your password shortly." y dos botones: "Back to sign in" (primario) y "Use a different email" (secundario, vuelve al formulario). Muestra SIEMPRE este mismo mensaje exista o no la cuenta (no revelar si el email está registrado)."

**S7 — Reset password.** "- `h2` "Set a new password" + "Choose a password you haven't used before. You'll use it to sign in from now on."
- "New password *" (con botón Show/Hide que afecta a ambos campos, `autocomplete="new-password"`, `minlength` 8, ayuda "At least 8 characters."). Ajusta la regla mínima a la política real de contraseñas del proyecto, no te quedes con el 8 si ya hay otra.
- "Confirm new password *". Si no coinciden: "The two passwords don't match." (`role="alert"`, 13px, `#8F1236`).
- Botón primario "Update password".
- Estado de éxito (`role="status"`): icono de check en cuadrado `#E3EEFB`, "Password updated", "Your password has been changed. Sign in with your new password to continue." y botón "Go to sign in".
- Conserva el token/email de reset del flujo actual (campos ocultos, etc.)."

**S8 — Accessibility and quality.** "- Elementos reales (`<label for>`, `<button>`, `<a>`), nunca divs clicables. Asteriscos de requerido con `aria-hidden`. Foco visible en todo.
- Comportamiento responsive verificado a 390px y 1440px, sin scroll horizontal.
- Textos de UI en inglés, tal cual figuran arriba (si el proyecto ya usa archivos de traducción, añade las claves ahí).
- Escribe o ajusta los tests existentes de auth si los hay, y corre el suite al final."

**S9 — Delivery.** "Al terminar, dame un resumen corto de los archivos tocados y cualquier decisión que hayas tenido que tomar. Haz commits pequeños por pantalla."

## Tasks

| ID | Specs | Route | Commit |
|----|-------|-------|--------|
| T1 | S1–S5, S8 | worker: shared auth layout (brand panel, tokens, fonts) + Sign in | — |
| T2 | S1–S4, S6, S8 | worker: Forgot password on the shared layout | — |
| T3 | S1–S4, S7, S8 | worker: Reset password on the shared layout | — |
| T4 | S8, S9 | parent: full auth suite, 390/1440 check, close | — |

## Log

**L1 — 2026-10-23, user's original request (verbatim):**

> Implementa el rediseño de las pantallas de autenticación de nuestro backoffice (Claesen + Electro Bertels). Son 3 pantallas: Sign in, Forgot password y Reset password.
>
> ## Antes de escribir código
> 1. Detecta el stack del proyecto (framework, motor de vistas/componentes, CSS, auth actual: Laravel Breeze/Fortify/Jetstream/custom, Socialite para Microsoft, etc.) y reutiliza lo que ya existe. No metas dependencias nuevas si no hacen falta.
> 2. Localiza las vistas/rutas/controladores actuales de login, forgot y reset password y modifícalos en lugar de crear duplicados.
> 3. Mantén intacta la lógica de autenticación existente (validación, throttling, "remember me", flujo de Microsoft SSO, tokens de reset). Esto es un cambio de UI; solo toca backend si algo del diseño lo exige y avísame antes.
>
> ## Layout (común a las 3 pantallas)
> Pantalla partida en dos paneles, `min-height: 100vh`, que en móvil (<~1000px) se apilan (marca arriba, formulario abajo).
>
> **Panel izquierdo (marca)**, fondo `#07111E`, texto blanco, padding `clamp(32px, 5vw, 72px)`, contenido en columna con `justify-content: space-between`:
> - Fondo: rejilla de líneas de 1px `rgba(255,255,255,.035)` cada 48px + resplandor `radial-gradient(60% 50% at 100% 0%, rgba(34,118,210,.30), transparent 70%)` + 4 círculos concéntricos decorativos en SVG (stroke blanco al 4-7%) abajo a la derecha, `aria-hidden`.
> - Arriba: logos de Claesen y Electro Bertels (altura 52px, `alt` descriptivo) separados por una línea vertical de 1px `rgba(255,255,255,.2)` y 36px de alto. Usa los archivos de logo que ya existan en el proyecto; si no, pídemelos (versión blanca/transparente, preferible SVG).
> - Centro: `h1` "Two trades, one shared backoffice" (Sora 600, `clamp(34px, 4vw, 52px)`, line-height 1.08, letter-spacing -0.02em, `text-wrap: balance`), debajo el párrafo "Sports lighting and electrical installations — one shared backoffice for both companies." (17px, `#C3CEDF`).
> - Debajo, dos tarjetas (radius 14, fondo `rgba(255,255,255,.045)`, borde `1px rgba(255,255,255,.10)`, padding 18/20) con un punto de 10px de color de marca + título (Sora 600, 16px) + descripción (15px, `#C3CEDF`):
>   - Punto `#2FD3E6` — "Claesen" — "Outdoor & stadium sports lighting, every level"
>   - Punto `#FF7A1A` — "Electro Bertels" — "Electrical installations & home automation for homes, businesses and industry"
> - IMPORTANTE: NO debe aparecer en ningún sitio el texto "Cash-flow guardian & AI auditor for every project".
> - Abajo: "© 2026 · All rights reserved" (13px, `#9FB0C7`). Usa el año actual dinámico si el proyecto ya lo hace.
>
> **Panel derecho (formulario)**, fondo `#F6F8FB`, formulario centrado vertical y horizontalmente, `max-width: 420px`, gap 28px entre bloques.
>
> ## Tokens
> - Fuentes (Google Fonts): Sora 500/600/700 para títulos, Inter 400/500/600 para el resto.
> - Azul primario `#1B66C0` (hover `#155AAD`), texto fuerte `#0B1727`, texto secundario `#4A586B`, borde de inputs `#C5CEDB`, divisor `#D3DAE5`, error texto `#8F1236` sobre `#FDEDF1` con borde `#F3B7C6`, ring de foco `3px solid #7DB3F2` con offset 2px.
> - Inputs y botones: alto 52-54px, radius 12px, fuente 16px (evita el zoom en iOS). Los controles interactivos miden mínimo 44px.
> - Todos los pares texto/fondo deben cumplir contraste 4.5:1.
>
> ## Pantalla 1: Sign in
> - `h2` "Sign in" (Sora 600, 34px) + "Welcome back. Enter your details to continue." (16px).
> - Mensaje de error de credenciales como aviso encima de los campos (`role="alert"`, icono de círculo con exclamación, texto "These credentials do not match our records."), no pegado bajo el email. Debe mostrarse con el error de validación real de auth.
> - Campo "Email address *" (`type=email`, `autocomplete="username"`, placeholder "you@company.com").
> - Campo "Password *" con botón "SHOW/HIDE" dentro del input (alterna `type` password/text; `aria-label` "Show password"/"Hide password"; 44px de alto, mayúsculas 12px).
> - Fila: checkbox "Remember me for 30 days" a la izquierda y enlace "Forgot password?" (azul, 600) a la derecha, con `flex-wrap` para que en pantallas estrechas baje de línea.
> - Botón primario "Sign in" (ancho completo, 54px).
> - Divisor "OR" (líneas a los lados).
> - Botón secundario blanco con borde "Sign in with Microsoft" con el logo de 4 cuadrados (#F25022, #7FBA00, #00A4EF, #FFB900), conectado al flujo SSO existente.
>
> ## Pantalla 2: Forgot password
> - Enlace arriba "‹ Back to sign in" (vuelve al login).
> - `h2` "Forgot your password?" + "Enter the email linked to your account and we'll send you a link to reset it."
> - Campo "Email address *" + botón primario "Send reset link".
> - Tras enviar, estado de confirmación (`role="status"`): icono de sobre en un cuadrado redondeado `#E3EEFB`, `h2` "Check your inbox", texto "If an account exists for **{email}**, you'll receive a link to reset your password shortly." y dos botones: "Back to sign in" (primario) y "Use a different email" (secundario, vuelve al formulario). Muestra SIEMPRE este mismo mensaje exista o no la cuenta (no revelar si el email está registrado).
>
> ## Pantalla 3: Reset password
> - `h2` "Set a new password" + "Choose a password you haven't used before. You'll use it to sign in from now on."
> - "New password *" (con botón Show/Hide que afecta a ambos campos, `autocomplete="new-password"`, `minlength` 8, ayuda "At least 8 characters."). Ajusta la regla mínima a la política real de contraseñas del proyecto, no te quedes con el 8 si ya hay otra.
> - "Confirm new password *". Si no coinciden: "The two passwords don't match." (`role="alert"`, 13px, `#8F1236`).
> - Botón primario "Update password".
> - Estado de éxito (`role="status"`): icono de check en cuadrado `#E3EEFB`, "Password updated", "Your password has been changed. Sign in with your new password to continue." y botón "Go to sign in".
> - Conserva el token/email de reset del flujo actual (campos ocultos, etc.).
>
> ## Accesibilidad y calidad
> - Elementos reales (`<label for>`, `<button>`, `<a>`), nunca divs clicables. Asteriscos de requerido con `aria-hidden`. Foco visible en todo.
> - Comportamiento responsive verificado a 390px y 1440px, sin scroll horizontal.
> - Textos de UI en inglés, tal cual figuran arriba (si el proyecto ya usa archivos de traducción, añade las claves ahí).
> - Escribe o ajusta los tests existentes de auth si los hay, y corre el suite al final.
>
> Al terminar, dame un resumen corto de los archivos tocados y cualquier decisión que hayas tenido que tomar. Haz commits pequeños por pantalla. D:\Proyectos\claesen-verlichting\diseno\Backoffice Login\backoffice-auth-design-reference.html Adjunto `backoffice-auth-design-reference.html`: la referencia visual exacta de 3 pantallas de autenticación de nuestro backoffice (Claesen + Electro Bertels): Sign in, Forgot password y Reset password. Navega entre ellas con la barra inferior.
>
> Implementa estas 3 pantallas en nuestro proyecto:
>
> 1. Primero detecta el stack (framework, vistas/componentes, CSS, sistema de auth: Breeze/Fortify/Jetstream/custom, SSO de Microsoft) y reutiliza lo existente. Modifica las vistas, rutas y controladores actuales de login/forgot/reset en lugar de duplicarlos.
> 2. El HTML es la fuente de verdad visual. Respeta tal cual tokens (variables CSS de `:root`), tipografías (Sora + Inter), espacios, radios, tamaños, textos en inglés y estados (error de credenciales, "Check your inbox", "Password updated", error de contraseñas que no coinciden). Trasládalo a las convenciones de nuestro proyecto (componentes, Tailwind/CSS, Blade/Vue/React, etc.); no copies el HTML en bruto.
> 3. IGNORA lo que es solo de demo: la barra `.ref-bar`, el `<script>` de demostración y los atajos `#signin/#forgot/#reset`. Usa las rutas reales de la app.
> 4. Mantén intacta la lógica de auth existente (validación, throttling, remember me, SSO de Microsoft, tokens de reset). El error de credenciales ("These credentials do not match our records.") debe mostrarse con el error real de validación. La pantalla de "Check your inbox" se muestra SIEMPRE tras enviar el formulario, exista o no la cuenta.
> 5. En Reset password, conserva el token/email del flujo actual y ajusta la regla mínima de 8 caracteres a nuestra política real.
> 6. El texto "Cash-flow guardian & AI auditor for every project" NO debe aparecer en ningún sitio.
> 7. Usa los logos que ya existan en el proyecto; si no, pídemelos.
> 8. Mantén la accesibilidad del HTML (labels, roles `alert`/`status`, foco visible, controles de 44px o más) y comprueba que se ve bien a 390px y 1440px.
> 9. Corre los tests de auth existentes y ajusta los que se rompan por el cambio de vistas.
>
> Al terminar, resume los archivos tocados y las decisiones que hayas tomado. Haz commits pequeños por pantalla.

**L2 — 2026-10-23, user decision:** implement on `electrobertels/trunk` (option "electrobertels/trunk (recomendado)"), not `main`. `main`'s uncommitted CLA-601 login edits stay untouched.

**L3 — 2026-10-23, evidence (parent):** existing auth on trunk = Filament 5 custom pages `Modules/Core/Filament/Pages/Auth/{Login,RequestPasswordReset,ResetPassword}.php`, views under `Modules/Core/resources/views/filament/auth/` (`brand-panel`, `login-theme`, `microsoft-login-button`, `microsoft-login-errors`, `other-panel-hint`, `password-reset-done`, `password-reset-sent`, `password-reset-requirements`), wired in `AdminPanelProvider` and `BertelsPanelProvider` (`->login()`, `->passwordReset()`, MFA). Logos with alpha already exist: `public/img/claesen-logo-login.png` (1609×978), `public/img/bertels-logo-dark.png` (1774×887). The forbidden string lives in `Modules/Core/lang/en/auth.php:19` (`brand_bullet_shared`). Linear is not callable from this session: commits use the existing login-redesign ticket prefix `CLA-601`.

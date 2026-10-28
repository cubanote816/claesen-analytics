---
version: alpha
name: Claesen Outdoor Lighting Platform
description: Sistema visual del backoffice CAFCA Intelligence Hub (Filament V5) para Claesen Verlichting BV, contratista belga de iluminación exterior.
omitted:
  - section: rounded
    reason: "No se ha customizado un radio de borde propio de marca — el panel usa el default de Filament V5/Tailwind sin overrides en tailwind.config.js."
  - section: spacing
    reason: "No se ha customizado una escala de espaciado propia de marca — el panel usa el default de Tailwind sin overrides en tailwind.config.js."
colors:
  primary: "#00aeef"
  success: "#a5d610"
  danger: "#e6007e"
  warning: "#fcd34d"
  accent: "#f97316"
  neutral-50: "#f8fafc"
  neutral-500: "#64748b"
  neutral-900: "#0f172a"
  industrial: "#0b0b0f"
typography:
  headline-lg:
    fontFamily: Outfit
    fontSize: 1.875rem
    fontWeight: 600
    lineHeight: 2.25rem
  headline-md:
    fontFamily: Outfit
    fontSize: 1.5rem
    fontWeight: 600
    lineHeight: 2rem
  body-md:
    fontFamily: Outfit
    fontSize: 1rem
    fontWeight: 400
    lineHeight: 1.5rem
  label-sm:
    fontFamily: Outfit
    fontSize: 0.875rem
    fontWeight: 500
    lineHeight: 1.25rem
  document-body:
    fontFamily: "Helvetica, Arial, sans-serif"
    fontSize: 8px
    lineHeight: 1.7
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.neutral-50}"
  button-success:
    backgroundColor: "{colors.success}"
    textColor: "{colors.neutral-900}"
  button-danger:
    backgroundColor: "{colors.danger}"
    textColor: "{colors.neutral-50}"
  button-warning:
    backgroundColor: "{colors.warning}"
    textColor: "{colors.neutral-900}"
  stat-card-signature:
    backgroundColor: "{colors.accent}"
    textColor: "{colors.neutral-50}"
    typography: "{typography.label-sm}"
  text-secondary:
    textColor: "{colors.neutral-500}"
    typography: "{typography.label-sm}"
  surface-dark:
    backgroundColor: "{colors.industrial}"
    textColor: "{colors.neutral-50}"
---

## Overview

CAFCA Intelligence Hub es el backoffice interno (Filament V5) que usa el equipo de Claesen Verlichting BV — contratistas de iluminación exterior en Bélgica — para gestión financiera, seguridad en obra, mantenimiento de instalaciones deportivas y campañas comerciales. La audiencia es 100% profesional interna (financial managers, project managers, técnicos, RR.HH.), nunca consumidor final: la interfaz debe sentirse precisa, densa en datos y confiable antes que llamativa, siguiendo la política de "Cero Complacencia" del proyecto ante riesgos financieros.

El tono es industrial-profesional: paneles de datos, tablas, KPIs financieros y checklists de seguridad son el contenido dominante. Un único acento cálido (**Claesen Orange**) se reserva para elevar momentos puntuales — tarjetas de estadísticas "premium" del dashboard y fondos con degradado sutil (`bg-mesh-signature`) — nunca para acciones o texto de uso general, que siguen el **Claesen Cyan** como color primario de interacción.

Los documentos oficiales (PDFs, emails transaccionales) son un contexto aparte y deliberadamente conservador: usan el membrete corporativo real de la empresa (`Modules/Core/resources/views/pdf/letterhead.blade.php`), tipografía sans-serif genérica (no Outfit, por compatibilidad de renderizado en lectores de PDF/email) y el logo oficial (`public/img/brand-logo-{light,dark}.png`).

## Colors

- **Primary — Claesen Cyan (`#00aeef`):** color de marca principal, usado para toda acción interactiva primaria (botones, links, foco, estado "info" de Filament). Es el único color con el que un usuario espera poder *actuar*.
- **Success — Claesen Lime (`#a5d610`):** confirmaciones, estados "aprobado"/"completado", indicadores positivos en dashboards financieros.
- **Danger — Claesen Magenta (`#e6007e`):** errores, alertas de riesgo financiero (WIP Trap, Watchdog), acciones destructivas.
- **Warning — Claesen Amber (`#fcd34d`):** estados intermedios/pendientes de validación, avisos no bloqueantes.
- **Accent — Claesen Orange (`#f97316`):** acento de marca "signature", exclusivo de elementos premium del dashboard (tarjetas de estadísticas, fondos con degradado). No se usa en botones ni texto de acción — mezclar este acento con el primary diluye la jerarquía de "qué es accionable".
- **Neutral (Slate, Tailwind):** escala completa `slate-50` a `slate-950` para texto, bordes y superficies. `neutral-50 (#f8fafc)` y `neutral-900 (#0f172a)` son los extremos usados como texto sobre fondo de color; `neutral-500 (#64748b)` para texto secundario/metadata.
- **Industrial (`#0b0b0f`):** casi-negro reservado para superficies de modo oscuro de alto contraste.

Estos 8 tokens son la fuente de verdad real del código: `app/Providers/Filament/AdminPanelProvider.php:128-134` (registro en Filament, con los mismos nombres "Claesen Cyan/Lime/Magenta/Amber" ya en los comentarios) y `tailwind.config.js` (paleta completa 50-950 por token + `industrial`). El acento `accent` vive en `resources/css/app.css` como `--color-claesen-orange`.

**Hallazgo real de contraste (`npx @google/design.md lint DESIGN.md`):** el tono base de `primary` y `accent` sobre texto claro no alcanza AA (2.42:1 y 2.68:1 respectivamente; `danger` queda apenas debajo, 4.30:1). Filament genera internamente una escala 50-950 a partir de cada hex base (`Color::hex()`) y en la práctica los botones sólidos suelen renderizar con un tono más oscuro que la base — pero eso no está verificado componente por componente contra estos números. Ajustar qué shade real usa cada botón es una decisión de producto aparte (tocaría `tailwind.config.js`/Filament), fuera del alcance de esta documentación.

## Typography

Toda la interfaz digital (paneles Filament, PWAs) usa **Outfit** como única familia tipográfica (`->font('Outfit')` en `AdminPanelProvider.php`, `--font-sans` en `app.css`). No hay una escala de tamaños propia de marca — se usa la escala default de Tailwind v4 sin overrides, aquí documentada en los niveles semánticos más usados (headlines de página/sección, cuerpo, labels de formulario/tabla).

Los **documentos oficiales** (PDFs vía `dompdf`, membrete corporativo) son la única excepción intencional: usan una sans-serif genérica del sistema a 8px para las columnas de datos de contacto, priorizando compatibilidad de renderizado sobre consistencia de marca — ver `letterhead.blade.php`.

## Layout

No hay una escala de espaciado propia de marca — el panel Filament usa el grid y los breakpoints default de Tailwind v4 sin overrides. Los formularios siguen el patrón estándar de secciones colapsables de Filament (`Filament\Schemas\Schema`), y las páginas de listado usan el ancho completo del panel salvo modales, que se acotan al tamaño default de Filament según su contenido.

## Elevation & Depth

La profundidad se transmite con dos mecanismos reales del código, no con una escala arbitraria de sombras:

1. **`--shadow-signature`/`--shadow-signature-dark`** (`app.css`): sombra suave de doble capa (contorno de 1px + sombra difusa de 40px) usada por `.glass-signature` — tarjetas con fondo translúcido (`bg-white/60`/`dark:bg-black/60`) y `backdrop-blur-2xl`, reservadas a componentes "premium" del dashboard (ej. `premium-stat-card.blade.php`).
2. **`.bg-mesh-signature`**: fondo con tres radiales sutiles (índigo, Claesen Orange, rosa) al 3-5% de opacidad en modo claro (10-15% en oscuro) — usado como telón de fondo decorativo en secciones hero del dashboard, nunca en contenido operativo denso (tablas, formularios).

El resto del panel (tablas, formularios, la inmensa mayoría de la superficie de la app) es intencionalmente plano — la jerarquía se transmite con color y bordes, no con sombra, siguiendo el estilo por defecto de Filament V5.

## Shapes

Sin overrides propios — el panel hereda el radio de borde default de los componentes de Filament V5 (`rounded-lg`/`rounded-xl` de Tailwind según el componente). No se ha definido una esquina de marca distintiva.

## Components

- **Botones/acciones primarias:** `background: primary`, texto claro (`neutral-50`) — único uso legítimo del Claesen Cyan como fondo sólido.
- **Botones de éxito/peligro/advertencia:** mapean 1:1 a `success`/`danger`/`warning`; el texto usa `neutral-900` sobre `warning` (fondo claro) y `neutral-50` sobre `success`/`danger` (fondos más saturados).
- **Tarjeta de estadística "signature"** (`premium-stat-card.blade.php`): fondo `accent` (Claesen Orange), texto `neutral-50`, tipografía `label-sm`. Es el único componente que usa el acento naranja como color sólido de fondo — todo lo demás lo usa solo como tinte decorativo de bajo porcentaje (ver Elevation & Depth).

## Auth screens (deliberate exception)

Las pantallas de autenticación del backoffice (Sign in hoy; Forgot / Reset password sobre el mismo layout) siguen su propio diseño aprobado, `odd/tasks/backoffice-auth-redesign.reference.html`, igual para el panel de Claesen y el de Electro Bertels. Es la única superficie del backoffice que se aparta de los tokens de arriba, y solo ahí:

- **Layout compartido de dos paneles:** marca a la izquierda (`#07111E`, logos de Claesen y Electro Bertels, rejilla + resplandor + círculos decorativos) y formulario a la derecha (`#F6F8FB`, `max-width: 420px`); apilados por debajo de ~1000px. Vive en `Modules/Core/resources/views/filament/auth/{brand-panel,login-theme}.blade.php`, enganchado a todas las rutas `filament.*.auth.*` en `AdminPanelProvider` (CSS plano, no Tailwind: el panel Bertels no tiene Vite theme).
- **Tipografía:** Sora 500/600/700 (títulos) + Inter 400/500/600 (resto), cargadas solo en estas pantallas vía `BunnyFontProvider` de Filament. Outfit sigue siendo la fuente del resto de la interfaz.
- **Color:** azul primario `#1B66C0` (hover `#155AAD`), texto `#0B1727` / `#4A586B`, borde de inputs `#C5CEDB`, error `#8F1236` sobre `#FDEDF1`, foco `3px solid #7DB3F2` con offset 2px. No usan el Claesen Cyan ni el naranja de Bertels: el azul es el mismo en ambos paneles. Siempre en modo claro (sin variante dark).
- **Controles:** inputs 52px y botones 54px, radius 12px, fuente 16px; todo control interactivo mide al menos 44px. Todos los pares texto/fondo cumplen 4.5:1.

## Bertels sidebar (deliberate exception: colors only)

El sidebar del panel Electro Bertels sigue el diseño aprobado `odd/tasks/bertels-sidebar.reference.html` solo en **color y estructura**; las **dimensiones y la tipografía son las del layout compartido** con el panel Claesen, de modo que cambiar de panel no produce un salto visual (decisión revisada: la primera versión usaba 180px y 11px/10px/8px, y se veía más pequeña que Claesen).

- **Estructura:** grupos de navegación nativos de Filament, colapsables y en este orden — ítems de primer nivel (Dashboard, KNX, Switch to Claesen), Publishing, Content, Site settings, Inbox. Declarados con `->navigationGroups()` en `BertelsPanelProvider` (etiquetas como closure, CLAUDE.md regla 10); las páginas del clúster Website se registran planas, cada una en su grupo (`WebsiteCluster::getNavigationItems()`, solo en el panel `bertels`).
- **Dimensiones y tipografía (compartidas con `admin`, no se redefinen):** ancho 20rem (el valor por defecto de Filament, que `admin` no cambia: `BertelsPanelProvider` no llama a `->sidebarWidth()`), fuente `->font('Outfit')`, logo `->brandLogoHeight('3rem')`, y todo lo demás (alto/padding/gap de ítem, etiqueta 14px/500, etiqueta de grupo, icono 24px, badge, topbar) son los valores por defecto de Filament que recibe Claesen. `BertelsSidebarTest` compara ambos paneles vía `Filament::getPanel()` y prohíbe declaraciones de tamaño en el tema.
- **Estilo:** un `<style id="bertels-sidebar-theme">` plano, enganchado a `HEAD_END` solo en este panel (`resources/views/filament/bertels/sidebar-theme.blade.php`; el panel no tiene Vite theme, así que no hay utilidades Tailwind). Solo declara colores (más el divisor de 1px que el tema de Claesen dibuja en modo oscuro, para que la caja de contenido mida lo mismo).
- **Espaciado de página:** el panel Bertels no tiene Vite theme, así que las reglas del theme de Claesen para la cabecera compartida (`.claesen-page-header-wrap`, fila de breadcrumbs con `margin-bottom: 0.75rem`, oculta bajo 640px) se portan, mismos valores y con ámbito `body.fi-panel-bertels`, a `resources/views/filament/bertels/layout-theme.blade.php`. Los comentarios Blade de estas vistas deben cerrarse siempre (`--}}`): uno sin cerrar imprimió texto visible sobre el layout y desplazó todo 48px.
- **Color (oscuro en modo claro y oscuro, sin variante `dark`):** fondo `#262628`, texto `#e8e6e3` (12.1:1), etiqueta de grupo e iconos `#9a9893` (5.2:1), hover `#2f2f32`, ítem activo `#f07a2a` sobre `#333336` (4.5:1), badge `#e8e6e3` sobre `#3a3a3d` (9.1:1). El naranja `#f07a2a` es el naranja de Electro Bertels, no el `accent` de Claesen.

## Do's and Don'ts

- Do usar Claesen Cyan (`primary`) para toda acción interactiva primaria — es el único color que un usuario debe poder interpretar como "esto es clickeable".
- Do reservar Claesen Orange (`accent`) exclusivamente a los componentes "signature" del dashboard (tarjetas premium, fondos mesh) — nunca como color de botón o texto de acción.
- Do mantener contraste WCAG AA (4.5:1 para texto normal) en cualquier combinación fondo/texto nueva — correr `npx @google/design.md lint DESIGN.md` antes de introducir un componente con color sólido.
- Do usar el membrete oficial (`@include('core::pdf.letterhead')`) en todo PDF nuevo — nunca reinventar el encabezado a mano.
- Don't introducir un color de marca nuevo fuera de los 8 tokens de esta paleta sin aprobación explícita del usuario — mismo criterio que las restricciones no negociables de `CLAUDE.md`.
- Don't usar Outfit en documentos PDF/email — la excepción de `document-body` es deliberada, no un descuido a corregir.
- Don't mezclar el acento naranja con el cyan primario en el mismo componente — diluye cuál es la acción principal de la pantalla.

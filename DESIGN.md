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

## Do's and Don'ts

- Do usar Claesen Cyan (`primary`) para toda acción interactiva primaria — es el único color que un usuario debe poder interpretar como "esto es clickeable".
- Do reservar Claesen Orange (`accent`) exclusivamente a los componentes "signature" del dashboard (tarjetas premium, fondos mesh) — nunca como color de botón o texto de acción.
- Do mantener contraste WCAG AA (4.5:1 para texto normal) en cualquier combinación fondo/texto nueva — correr `npx @google/design.md lint DESIGN.md` antes de introducir un componente con color sólido.
- Do usar el membrete oficial (`@include('core::pdf.letterhead')`) en todo PDF nuevo — nunca reinventar el encabezado a mano.
- Don't introducir un color de marca nuevo fuera de los 8 tokens de esta paleta sin aprobación explícita del usuario — mismo criterio que las restricciones no negociables de `CLAUDE.md`.
- Don't usar Outfit en documentos PDF/email — la excepción de `document-body` es deliberada, no un descuido a corregir.
- Don't mezclar el acento naranja con el cyan primario en el mismo componente — diluye cuál es la acción principal de la pantalla.

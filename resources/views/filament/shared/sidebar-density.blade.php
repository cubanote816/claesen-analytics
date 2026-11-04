{{--
    Compact sidebar density shared by both panels (CLA-643, DESIGN.md "Sidebar density").

    Reference: Google AI Studio's navigation — small uppercase section headers, ~32px rows, 20px
    icons, little air between sections. Filament's defaults (p-2 rows, gap-y-7 groups, py-8 nav,
    24px icons) left too much space between elements.

    Registered on PanelsRenderHook::HEAD_END by BOTH AdminPanelProvider and BertelsPanelProvider, so
    the two sidebars keep identical dimensions and switching panels causes no visual jump. Since
    both panels carry the same block, the rules need no panel scope.

    Plain unlayered CSS, never Tailwind utilities: the bertels panel has no ->viteTheme() (CLAUDE.md
    rule 11), and unlayered rules win over Filament's layered ones without raising specificity.

    SIZES ONLY: colours belong to each panel's theme (Claesen's Vite theme, Bertels' colour skin).
--}}
<style id="sidebar-density">
    .fi-sidebar-nav {
        padding: 1rem;
        row-gap: 1rem;
    }

    .fi-sidebar-nav-groups {
        row-gap: 1rem;
    }

    .fi-sidebar-group,
    .fi-sidebar-group-items,
    .fi-sidebar-sub-group-items {
        row-gap: 0.125rem;
    }

    /* Section header: small uppercase label. The header icon only shows while the sidebar is
       expanded; collapsed, the group's dropdown trigger keeps its own icon. */
    .fi-sidebar-group-btn {
        column-gap: 0.5rem;
        padding: 0.25rem 0.5rem;
    }

    .fi-sidebar-group-btn > .fi-icon {
        display: none;
    }

    .fi-sidebar-group-label {
        font-size: 0.6875rem;
        line-height: 1rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .fi-sidebar-group-collapse-btn {
        margin-block: -0.375rem;
    }

    /* Rows: ~32px high, 20px icon (or the grouped-item marker in its place). */
    .fi-sidebar-item-btn {
        column-gap: 0.75rem;
        padding: 0.375rem 0.5rem;
    }

    .fi-sidebar-item-btn > .fi-icon,
    .fi-sidebar-item-grouped-border {
        width: 1.25rem;
        height: 1.25rem;
    }
</style>

{{--
    Bertels panel sidebar (design source of truth: odd/tasks/bertels-sidebar.reference.html).

    Registered on PanelsRenderHook::HEAD_END by BertelsPanelProvider only — the Claesen admin
    panel never receives this style block, which is what keeps its sidebar untouched.

    Every selector (and the --bsb-* properties) is scoped to `body.fi-panel-bertels`: both panels
    run in SPA mode and Livewire's navigate merges <head>, so an unscoped rule would survive a
    panel switch and restyle the other panel.

    Plain CSS, never Tailwind utilities: the bertels panel has no ->viteTheme(), so utilities
    used only by a view are never generated here (CLAUDE.md rule 11).

    COLOURS ONLY: every dimension and typography value (sidebar width, item height/padding/gap,
    label / group / badge sizes, icon size, logo height, font family, page spacing) is Filament's
    own default, the same one the Claesen admin panel gets, so switching panels causes no visual
    jump (DESIGN.md). Do not add sizes here; change them on the panel (->sidebarWidth(), ->font(),
    ->brandLogoHeight()).

    The sidebar is dark in BOTH colour modes, like the reference, so no rule has a `.dark` variant
    (except the 1px divider that mirrors Claesen's). Contrast (WCAG): text #e8e6e3 on #262628
    12.1:1, group label / icon #9a9893 on #262628 5.2:1, active #f07a2a on #333336 4.5:1, badge
    #e8e6e3 on #3a3a3d 9.1:1.
--}}
<style id="bertels-sidebar-theme">
    body.fi-panel-bertels {
        --bsb-bg: #262628;
        --bsb-text: #e8e6e3;
        --bsb-muted: #9a9893;
        --bsb-hover: #2f2f32;
        --bsb-active-bg: #333336;
        --bsb-accent: #f07a2a;
        --bsb-badge-bg: #3a3a3d;
    }

    /* Surface. The body class scopes every rule to this panel and keeps specificity above Filament's `.dark` variants. */
    body.fi-panel-bertels .fi-sidebar {
        background-color: var(--bsb-bg);
        color: var(--bsb-text);
        --tw-ring-color: transparent;
        box-shadow: none;
    }

    /* The Claesen theme draws a 1px divider on the dark sidebar; mirror it (in a palette colour) so the content box is the same width. */
    body.fi-panel-bertels:where(.dark *) .fi-sidebar {
        border-right: 1px solid var(--bsb-hover);
    }

    body.fi-panel-bertels .fi-sidebar-header {
        background-color: var(--bsb-bg);
        box-shadow: none;
    }

    body.fi-panel-bertels .fi-sidebar-close-overlay {
        background-color: rgba(0, 0, 0, 0.6);
    }

    /* Group header: muted label + the chevron Filament already renders (it rotates when collapsed). */
    body.fi-panel-bertels .fi-sidebar-group-btn {
        user-select: none;
    }

    body.fi-panel-bertels .fi-sidebar-group-label,
    body.fi-panel-bertels .fi-sidebar-group-collapse-btn,
    body.fi-panel-bertels .fi-sidebar-group-collapse-btn .fi-icon {
        color: var(--bsb-muted);
    }

    /* Item: light label, muted icon. */
    body.fi-panel-bertels .fi-sidebar-item-btn {
        color: inherit;
    }

    body.fi-panel-bertels .fi-sidebar-item-btn:hover,
    body.fi-panel-bertels .fi-sidebar-item-btn:focus-visible {
        background-color: var(--bsb-hover);
    }

    body.fi-panel-bertels .fi-sidebar-item-label {
        color: var(--bsb-text);
    }

    body.fi-panel-bertels .fi-sidebar-item-icon,
    body.fi-panel-bertels .fi-sidebar-item-btn > .fi-icon {
        color: var(--bsb-muted);
    }

    /* Active item: orange label + icon on a slightly lighter row. */
    body.fi-panel-bertels .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background-color: var(--bsb-active-bg);
    }

    body.fi-panel-bertels .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-label,
    body.fi-panel-bertels .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-icon,
    body.fi-panel-bertels .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-icon {
        color: var(--bsb-accent);
    }

    /* Pending-count badge: grey chip, whatever colour the page asked for (warning / danger). */
    body.fi-panel-bertels .fi-sidebar-item-btn .fi-badge {
        background-color: var(--bsb-badge-bg);
        color: var(--bsb-text);
        --tw-ring-shadow: 0 0 #0000;
        box-shadow: none;
    }

    body.fi-panel-bertels .fi-sidebar-item-btn .fi-badge .fi-badge-label {
        color: var(--bsb-text);
    }

    /* Keyboard focus stays visible on the dark surface. */
    body.fi-panel-bertels .fi-sidebar :focus-visible {
        outline: 2px solid var(--bsb-accent);
        outline-offset: 1px;
    }
</style>

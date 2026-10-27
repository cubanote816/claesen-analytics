{{--
    Bertels panel sidebar (design source of truth: odd/tasks/bertels-sidebar.reference.html).

    Registered on PanelsRenderHook::HEAD_END by BertelsPanelProvider only — the Claesen admin
    panel never receives this <style id="bertels-sidebar-theme">
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

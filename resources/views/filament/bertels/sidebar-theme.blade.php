{{--
    Bertels panel sidebar (design source of truth: odd/tasks/bertels-sidebar.reference.html).

    Registered on PanelsRenderHook::HEAD_END by BertelsPanelProvider only — the Claesen admin
    panel never receives this <style>, which is what keeps its sidebar untouched.

    Plain <style>, never Tailwind utilities: the bertels panel has no ->viteTheme(), so utilities
    used only by a view are never generated here (CLAUDE.md rule 11). It restyles Filament's own
    sidebar markup (groups, items, badges, active state) rather than replacing it, so the
    collapsible groups, the active state and the mobile drawer keep working natively.

    The sidebar is dark in BOTH colour modes, like the reference: it is a fixed brand surface, not
    part of the page theme, so none of the rules below has a `.dark` variant. The width (180px)
    comes from ->sidebarWidth() on the panel. Contrast (WCAG): text #e8e6e3 on #262628 12.1:1,
    group label / icon #9a9893 on #262628 5.2:1, active #f07a2a on #333336 4.5:1, badge #e8e6e3 on
    #3a3a3d 9.1:1.
--}}
<style id="bertels-sidebar-theme">
    :root {
        --bsb-bg: #262628;
        --bsb-text: #e8e6e3;
        --bsb-muted: #9a9893;
        --bsb-hover: #2f2f32;
        --bsb-active-bg: #333336;
        --bsb-accent: #f07a2a;
        --bsb-badge-bg: #3a3a3d;
    }

    /* Surface. The extra `html` raises specificity over Filament's `.dark` variants. */
    html .fi-sidebar {
        background-color: var(--bsb-bg);
        color: var(--bsb-text);
        font-weight: 600;
        font-size: 11px;
        --tw-ring-color: transparent;
        box-shadow: none;
    }

    html .fi-sidebar-header {
        background-color: var(--bsb-bg);
        box-shadow: none;
    }

    html .fi-sidebar-close-overlay {
        background-color: rgba(0, 0, 0, 0.6);
    }

    html .fi-sidebar-nav {
        padding: 22px 10px;
        row-gap: 14px;
    }

    html .fi-sidebar-nav-groups {
        margin-inline: 0;
        row-gap: 14px;
    }

    html .fi-sidebar-group-items {
        row-gap: 2px;
    }

    /* Group header: muted 10px/500 label + the chevron-up Filament already renders (it rotates when collapsed). */
    html .fi-sidebar-group-btn {
        padding: 4px 5px;
        column-gap: 0;
        justify-content: space-between;
        user-select: none;
    }

    html .fi-sidebar-group-label {
        flex: 1;
        font-size: 10px;
        font-weight: 500;
        line-height: 1.4;
        color: var(--bsb-muted);
    }

    html .fi-sidebar-group-collapse-btn {
        width: 18px;
        height: 18px;
        color: var(--bsb-muted);
    }

    html .fi-sidebar-group-collapse-btn .fi-icon {
        width: 11px;
        height: 11px;
        color: var(--bsb-muted);
    }

    /* Item: compact row, muted 13px icon, 5px radius. */
    html .fi-sidebar-item-btn {
        column-gap: 9px;
        padding: 6px 7px;
        border-radius: 5px;
        color: inherit;
    }

    html .fi-sidebar-item-btn:hover,
    html .fi-sidebar-item-btn:focus-visible {
        background-color: var(--bsb-hover);
    }

    html .fi-sidebar-item-label {
        flex: 1;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.3;
        color: var(--bsb-text);
    }

    html .fi-sidebar-item-icon,
    html .fi-sidebar-item-btn > .fi-icon {
        width: 13px;
        height: 13px;
        flex: none;
        color: var(--bsb-muted);
    }

    /* Active item: orange label + icon on a slightly lighter row. */
    html .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background-color: var(--bsb-active-bg);
    }

    html .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-label,
    html .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-icon,
    html .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-icon {
        color: var(--bsb-accent);
    }

    /* Pending-count badge: small grey chip, whatever colour the page asked for (warning / danger).
       The container's own 1.5 line-height would make badged rows taller than plain ones. */
    html .fi-sidebar-item-badge-ctn {
        display: flex;
        line-height: 1;
    }

    html .fi-sidebar-item-btn .fi-badge {
        min-width: 0;
        padding: 1px 5px;
        border-radius: 3px;
        font-size: 8px;
        font-weight: 600;
        line-height: 1.4;
        background-color: var(--bsb-badge-bg);
        color: var(--bsb-text);
        --tw-ring-shadow: 0 0 #0000;
        box-shadow: none;
    }

    html .fi-sidebar-item-btn .fi-badge .fi-badge-label {
        font-size: 8px;
        color: var(--bsb-text);
    }

    /* Keyboard focus stays visible on the dark surface. */
    html .fi-sidebar :focus-visible {
        outline: 2px solid var(--bsb-accent);
        outline-offset: 1px;
    }

    /* Drawer (below lg): same look, but touch-sized rows. */
    @media (max-width: 63.99rem) {
        html .fi-sidebar-item-btn,
        html .fi-sidebar-group-btn {
            min-height: 40px;
            align-items: center;
        }
    }
</style>

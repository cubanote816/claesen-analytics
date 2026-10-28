{{--
    Bertels panel: page-header spacing shared with the Claesen admin panel.

    The Claesen Vite theme (resources/css/filament/admin/theme.css, "Breadcrumb: own full-width row")
    styles the .claesen-page-header-wrap / .claesen-page-header-breadcrumbs markup that the app-wide
    header override (resources/views/vendor/filament-panels/components/header/index.blade.php) renders
    on BOTH panels. The bertels panel has no ->viteTheme(), so those rules never reached it: its
    breadcrumb row had no bottom margin and was shown on small screens too. Same values as the theme,
    scoped to the bertels body class (SPA navigation merges <head>). Spacing only, no colours.
--}}
<style id="bertels-layout-theme">
    body.fi-panel-bertels .claesen-page-header-wrap {
        display: flex;
        flex-direction: column;
    }

    body.fi-panel-bertels .claesen-page-header-breadcrumbs {
        display: none;
        margin-bottom: 0.75rem;
    }

    @media (min-width: 640px) {
        body.fi-panel-bertels .claesen-page-header-breadcrumbs {
            display: block;
        }
    }
</style>

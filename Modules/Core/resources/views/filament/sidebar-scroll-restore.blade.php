<script>
(function () {
    var STORAGE_KEY = 'fi-sidebar-scroll-top';

    function getSidebarNav() {
        return document.querySelector('.fi-sidebar-nav');
    }

    document.addEventListener('livewire:navigate', function () {
        var nav = getSidebarNav();
        if (nav) {
            sessionStorage.setItem(STORAGE_KEY, String(nav.scrollTop));
        }
    });

    document.addEventListener('livewire:navigated', function () {
        var saved = sessionStorage.getItem(STORAGE_KEY);
        if (saved === null) {
            return;
        }
        var target = parseInt(saved, 10);

        function apply() {
            var nav = getSidebarNav();
            if (nav) {
                nav.scrollTop = target;
            }
        }

        // Same 10ms delay Filament's own scroll-sidebar.js uses: right after the
        // swap, collapsible groups/layout haven't settled yet, so scrollHeight
        // is still too small and the assignment below gets silently clamped to 0.
        // A second, later attempt covers cases where 10ms still isn't enough
        // (e.g. a collapsible group animating open).
        setTimeout(apply, 10);
        setTimeout(apply, 100);
    });
}());
</script>

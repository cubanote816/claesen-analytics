<script>
(function () {
    function scrollToEnd() {
        var list = document.querySelector('.fi-breadcrumbs-list');
        if (list) {
            list.scrollLeft = list.scrollWidth;
        }
    }

    // Runs once at true page load (this script tag is part of the initial HTML —
    // see feedback_livewire_wire_navigate_alpine memory for why a <script> tag
    // wouldn't re-run if inserted via a wire:navigate morph). The listener below
    // is what makes it work for every *subsequent* soft navigation too: it's
    // registered once here and reacts to Livewire's own global navigation
    // events forever, same pattern as sidebar-scroll-restore.blade.php.
    scrollToEnd();
    document.addEventListener('livewire:navigated', scrollToEnd);
}());
</script>

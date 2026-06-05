<div
    id="prospect-fab"
    style="display:none;position:fixed;bottom:2.5rem;right:2.5rem;z-index:9999;width:72px;height:72px;background:#ea580c;border-radius:18px;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 8px 32px rgba(234,88,12,0.5);border:none;flex-direction:column;"
    onclick="window.__prospectsStartMailing()"
    title="Mailing campagne starten"
>
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="white" style="width:32px;height:32px;transform:rotate(-45deg);">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
    </svg>
    <span id="prospect-fab-badge" style="position:absolute;top:-8px;right:-8px;background:white;color:#ea580c;font-size:11px;font-weight:700;min-width:22px;height:22px;border-radius:11px;display:flex;align-items:center;justify-content:center;padding:0 4px;box-shadow:0 2px 6px rgba(0,0,0,0.25);border:2px solid #ea580c;">0</span>
</div>

<script>
(function () {
    'use strict';

    var fab   = document.getElementById('prospect-fab');
    var badge = document.getElementById('prospect-fab-badge');
    var lastN = -1; // -1 forces first sync() to always run

    function isProspectsPage() {
        return window.location.pathname.replace(/\/$/, '') === '/prospects';
    }

    function sync() {
        if (!isProspectsPage()) {
            if (lastN !== 0) { fab.style.display = 'none'; lastN = 0; }
            return;
        }

        var n = document.querySelectorAll('table tbody input[type="checkbox"]:checked').length;
        if (n === lastN) return;
        lastN = n;
        fab.style.display = n > 0 ? 'flex' : 'none';
        badge.textContent = n;
    }

    // Runs sync() after a Livewire commit settles: morph done, PHP-dispatched
    // browser events fired, Alpine reactive effects flushed.
    // setTimeout(0) is a macro-task — runs after all pending micro-tasks
    // (including the 3x-nested queueMicrotask Livewire uses to dispatch PHP events
    // and Alpine's own reactive-effect micro-tasks).
    function syncAfterSettle() {
        setTimeout(sync, 0);
    }

    window.__prospectsStartMailing = function () {
        var table = document.querySelector('table');
        if (!table) return;

        var el = table.closest('[wire\\:id]');
        if (!el) {
            var all = document.querySelectorAll('[wire\\:id]');
            for (var i = 0; i < all.length; i++) {
                if (all[i].contains(table)) { el = all[i]; break; }
            }
        }
        if (!el) return;

        var component = window.Livewire && window.Livewire.find(el.getAttribute('wire:id'));
        if (!component) return;

        // Collect selected IDs from the DOM (same source of truth as Alpine's selectedRecords Set).
        // Then sync them to PHP via $wire.set() BEFORE calling mountAction, mirroring exactly
        // what Filament's own Alpine table.mountAction() does. Without this, the floating button
        // bypasses Alpine's mountAction() and PHP always reads the stale snapshot value ([]).
        var selectedIds = Array.from(
            document.querySelectorAll('table tbody input[type="checkbox"]:checked')
        ).map(function (cb) { return cb.value; });

        component.set('isTrackingDeselectedTableRecords', false, false);
        component.set('selectedTableRecords', selectedIds, false);
        component.set('deselectedTableRecords', [], false);

        // mountAction with Filament V5 context (table + bulk) — replaces deprecated mountTableBulkAction.
        component.call('mountAction', 'execute_campaign', {}, { table: true, bulk: true });
    };

    // Update badge on user checkbox interaction (same tab, no Livewire round-trip).
    document.addEventListener('change', function (e) {
        if (e.target && e.target.type === 'checkbox') sync();
    });

    // livewire:update does not exist in Livewire 3 (it was Livewire 2 only).
    // Livewire.hook('commit') is the Livewire 3 equivalent: fires once per
    // network round-trip after the response is processed. The succeed() callback
    // fires after the DOM is morphed and effects are queued. syncAfterSettle()
    // uses setTimeout(0) so it runs after PHP-dispatched browser events AND
    // Alpine's reactive micro-tasks have both completed, giving the correct DOM state.
    window.addEventListener('livewire:initialized', function () {
        Livewire.hook('commit', function (hookData) {
            hookData.succeed(syncAfterSettle);
        });
    });

    document.addEventListener('livewire:navigated', sync);
    document.addEventListener('livewire:load',      sync);

    sync();
}());
</script>

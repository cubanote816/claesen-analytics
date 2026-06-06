<button
    id="prospect-fab"
    type="button"
    style="display:none;position:fixed;bottom:4rem;right:2rem;z-index:9999;align-items:center;gap:0.5rem;background:#00aeef;color:white;padding:0.625rem 1.25rem;border-radius:9999px;border:none;cursor:pointer;font-size:0.875rem;font-weight:600;white-space:nowrap;box-shadow:0 4px 16px rgba(0,174,239,0.4);"
    onclick="window.__prospectsStartMailing()"
    title="{{ __('prospects::resource.actions.execute_campaign.label') }}"
>
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="white" style="width:18px;height:18px;flex-shrink:0;transform:rotate(-45deg);">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
    </svg>
    <span>{{ __('prospects::resource.actions.execute_campaign.label') }}</span>
    <span id="prospect-fab-badge" style="background:white;color:#00aeef;font-size:11px;font-weight:700;min-width:20px;height:20px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;padding:0 4px;">0</span>
</button>

<script>
(function () {
    'use strict';

    var fab   = document.getElementById('prospect-fab');
    var badge = document.getElementById('prospect-fab-badge');
    var lastN = -1; // -1 forces first sync() to always run

    function isProspectsPage() {
        return window.location.pathname.replace(/\/$/, '') === '/prospects';
    }

    // fi-ta-record-checkbox is Filament's class for per-row checkboxes only.
    // It excludes the "select all page" header checkbox (fi-ta-page-checkbox)
    // and is scoped to the Livewire component root so other tables on the page
    // (if any) are never counted.
    function getCheckedRecordCount() {
        var root = document.querySelector('[wire\\:id]');
        var scope = root || document;
        return scope.querySelectorAll('input.fi-ta-record-checkbox:checked').length;
    }

    function sync() {
        if (!isProspectsPage()) {
            if (lastN !== 0) { fab.style.display = 'none'; lastN = 0; }
            return;
        }

        var n = getCheckedRecordCount();
        if (n === lastN) return;
        lastN = n;
        fab.style.display = n > 0 ? 'flex' : 'none';
        badge.textContent = n;
    }

    // Runs sync() after a Livewire commit fully settles: DOM morphed, PHP-dispatched
    // browser events fired (via Livewire's 3× nested queueMicrotask), and Alpine's
    // reactive x-bind:checked effects flushed (also micro-tasks).
    // setTimeout(0) is a macro-task so it always runs after all of those.
    function syncAfterSettle() {
        setTimeout(sync, 0);
    }

    window.__prospectsStartMailing = function () {
        // Locate the Livewire component that owns the prospects table.
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

        // Read selected IDs from the component's own record checkboxes (fi-ta-record-checkbox),
        // scoped to the Livewire root so other page tables are excluded and the header
        // "select all" checkbox (fi-ta-page-checkbox) is never picked up.
        var selectedIds = Array.from(
            el.querySelectorAll('input.fi-ta-record-checkbox:checked')
        ).map(function (cb) { return cb.value; });

        // Sync selection to PHP before mounting — mirrors exactly what Filament's own
        // Alpine table.mountAction() does via $wire.set(). The floating button bypasses
        // Alpine's mountAction(), so without this PHP always receives selectedTableRecords=[].
        component.set('isTrackingDeselectedTableRecords', false, false);
        component.set('selectedTableRecords', selectedIds, false);
        component.set('deselectedTableRecords', [], false);

        // Filament V5: mountAction with table+bulk context (replaces deprecated mountTableBulkAction).
        component.call('mountAction', 'execute_campaign', {}, { table: true, bulk: true });
    };

    // Badge update on direct checkbox interaction (no Livewire round-trip needed).
    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList.contains('fi-ta-record-checkbox')) sync();
    });

    // livewire:update does not exist in Livewire 3 (it was a Livewire 2 event).
    // Livewire.hook('commit') fires once per network round-trip. The succeed()
    // callback fires after DOM is morphed and effects are queued; syncAfterSettle()
    // defers to a macro-task so Alpine's reactive effects have already run.
    // Guard prevents duplicate hook registration if the view re-renders.
    window.addEventListener('livewire:initialized', function () {
        if (window.__prospectsFabHookRegistered) return;
        window.__prospectsFabHookRegistered = true;

        Livewire.hook('commit', function (hookData) {
            hookData.succeed(syncAfterSettle);
        });
    });

    document.addEventListener('livewire:navigated', function () {
        window.__prospectsFabHookRegistered = false; // reset on SPA navigation
        sync();
    });
    document.addEventListener('livewire:load', sync);

    sync();
}());
</script>

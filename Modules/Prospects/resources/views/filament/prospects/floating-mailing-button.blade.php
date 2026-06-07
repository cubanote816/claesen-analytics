<style>
#prospect-fab {
    display: none;
    position: fixed;
    right: 32px;
    bottom: 32px;
    z-index: 9999;
    align-items: center;
    gap: 0.5rem;
    background: #00aeef;
    color: white;
    padding: 0.625rem 1.25rem;
    border-radius: 9999px;
    border: none;
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 600;
    white-space: nowrap;
    box-shadow: 0 4px 16px rgba(0,174,239,0.4);
}
#prospect-fab.is-visible {
    display: inline-flex;
}
@media (max-width: 640px) {
    #prospect-fab.is-visible {
        left: 16px;
        right: 16px;
        bottom: 16px;
        justify-content: center;
    }
}
</style>

<button
    id="prospect-fab"
    type="button"
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
    var lastN = -1;

    // Move to document.body to escape any stacking context created by
    // Filament's panel containers (transform / will-change / filter),
    // which would break position:fixed relative to the viewport.
    // On SPA re-navigation, remove any stale instance before appending the fresh one.
    document.querySelectorAll('body > #prospect-fab').forEach(function (el) {
        if (el !== fab) el.remove();
    });
    document.body.appendChild(fab);

    function isProspectsPage() {
        return window.location.pathname.replace(/\/$/, '') === '/prospects';
    }

    // Find the Livewire component that owns the prospects table.
    // document.querySelector('[wire:id]') returns the first component in the DOM
    // (the topbar), not the resource page. We must find the one containing <table>.
    function getProspectsRoot() {
        var all = document.querySelectorAll('[wire\\:id]');
        for (var i = 0; i < all.length; i++) {
            if (all[i].querySelector('table')) return all[i];
        }
        return null;
    }

    function getCheckedRecordCount() {
        var scope = getProspectsRoot() || document;

        // Individual per-row selection
        var n = scope.querySelectorAll('input.fi-ta-record-checkbox:checked').length;
        if (n > 0) return n;

        // Page-select mode: fall back to counting tbody rows
        if (scope.querySelector('input.fi-ta-page-checkbox:checked')) {
            return scope.querySelectorAll('table tbody tr').length || 1;
        }

        return 0;
    }

    function sync() {
        if (!isProspectsPage()) {
            if (lastN !== 0) { fab.classList.remove('is-visible'); lastN = 0; }
            return;
        }

        var n = getCheckedRecordCount();
        if (n === lastN) return;
        lastN = n;
        if (n > 0) { fab.classList.add('is-visible'); }
        else        { fab.classList.remove('is-visible'); }
        badge.textContent = n;
    }

    // Runs sync() after Alpine's reactive x-bind:checked effects have flushed.
    // setTimeout(0) is a macro-task so it always runs after Alpine's micro-tasks.
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

        // Read selected IDs from individual per-row checkboxes.
        var selectedIds = Array.from(
            el.querySelectorAll('input.fi-ta-record-checkbox:checked')
        ).map(function (cb) { return cb.value; });

        // Only override Livewire's selection state for individual-checkbox mode.
        // In page-select mode (fi-ta-page-checkbox checked, individual checkboxes
        // absent from DOM), selectedIds=[] — overriding would clear Filament's own
        // selectedTableRecords, which already holds all page IDs.
        if (selectedIds.length > 0) {
            component.set('isTrackingDeselectedTableRecords', false, false);
            component.set('selectedTableRecords', selectedIds, false);
            component.set('deselectedTableRecords', [], false);
        }

        component.call('mountAction', 'execute_campaign', {}, { table: true, bulk: true });
    };

    // Detect table row/checkbox clicks. In Filament V5, clicking a row fires on
    // <tr>/<td>/<span> — not on the <input> itself — so we cannot filter by
    // fi-ta-record-checkbox class. Scoping to [wire:id] avoids firing on nav
    // clicks outside the table. Capturing phase runs before Alpine's own handlers.
    document.addEventListener('click', function (e) {
        if (!isProspectsPage()) return;
        if (e.target.closest('[wire\\:id]')) syncAfterSettle();
    }, true);

    // Livewire.hook('commit') fires once per network round-trip (pagination, filters,
    // tab changes). Guard prevents duplicate registration on re-render.
    // With Filament SPA (->spa()), livewire:initialized fires BEFORE the BODY_END
    // script loads, so we register immediately when Livewire is present and fall
    // back to the event only for the hard-refresh case.
    function registerLivewireHook() {
        if (window.__prospectsFabHookRegistered) return;
        window.__prospectsFabHookRegistered = true;

        Livewire.hook('commit', function (hookData) {
            hookData.succeed(syncAfterSettle);
        });
    }

    if (window.Livewire) {
        registerLivewireHook();
    } else {
        window.addEventListener('livewire:initialized', registerLivewireHook);
    }

    document.addEventListener('livewire:navigated', function () {
        window.__prospectsFabHookRegistered = false;
        sync();
    });
    document.addEventListener('livewire:load', sync);

    sync();
    setInterval(function () { if (isProspectsPage()) sync(); }, 200);
}());
</script>

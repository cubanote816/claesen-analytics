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
    var lastN = 0;

    // ✅ Guard: solo activar en /admin/prospects
    function isProspectsPage() {
        return window.location.pathname.replace(/\/$/, '') === '/admin/prospects';
    }

    function sync() {
        // Si no estamos en la página correcta → ocultar y salir
        if (!isProspectsPage()) {
            if (lastN !== 0) { fab.style.display = 'none'; lastN = 0; }
            return;
        }

        var n = document.querySelectorAll('table tbody input[type="checkbox"]:checked').length;
        if (n === lastN) return; // sin cambios → no hacer nada
        lastN = n;
        fab.style.display = n > 0 ? 'flex' : 'none';
        badge.textContent = n;
    }

    window.__prospectsStartMailing = function () {
        var table = document.querySelector('table');
        if (!table) return;

        // Subir desde la tabla para encontrar el componente Livewire correcto
        var el = table.closest('[wire\\:id]');
        if (!el) {
            var all = document.querySelectorAll('[wire\\:id]');
            for (var i = 0; i < all.length; i++) {
                if (all[i].contains(table)) { el = all[i]; break; }
            }
        }

        if (!el) return;

        var component = window.Livewire && window.Livewire.find(el.getAttribute('wire:id'));
        if (component) component.call('mountTableBulkAction', 'execute_campaign');
    };

    // Eventos seguros — sin MutationObserver, sin setInterval
    document.addEventListener('change', function (e) {
        if (e.target && e.target.type === 'checkbox') sync();
    });

    // Livewire 3: se dispara UNA VEZ por ciclo, no en bucle
    document.addEventListener('livewire:update',    sync);
    document.addEventListener('livewire:navigated', sync); // ocultar al navegar fuera
    document.addEventListener('livewire:load',      sync);

    sync(); // verificación inicial
}());
</script>

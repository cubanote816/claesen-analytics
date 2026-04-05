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

    var fab    = document.getElementById('prospect-fab');
    var badge  = document.getElementById('prospect-fab-badge');
    var observer = null;

    function sync() {
        if (!fab) return;

        // 1. Encontrar el contenedor principal de la tabla (Source of Truth)
        var tableContainer = document.querySelector('[x-data^="filamentTable"]');
        if (!tableContainer) {
            fab.style.display = 'none';
            return;
        }

        var n = 0;
        
        // 2. Mirror de la lógica de Filament: ¿Existe el indicador de selección? 
        // En Filament V5, la barra ".fi-ta-selection-indicator" solo se muestra cuando hay registros
        var indicator = tableContainer.querySelector('.fi-ta-selection-indicator');
        var isVisible = indicator && !indicator.hasAttribute('hidden') && window.getComputedStyle(indicator).display !== 'none';

        if (isVisible) {
            // Intentar extraer el número del texto si Alpine no responde (para el badge)
            var match = indicator.textContent.match(/\d+/);
            n = match ? parseInt(match[0]) : 1;
        } else {
            // Fallback: Si no hay indicador, no hay botón flotante
            n = 0;
        }
        
        // 3. Match EXACTO de visibilidad
        fab.style.display = n > 0 ? 'flex' : 'none';
        if (badge) badge.textContent = n;
        
        // Si no tenemos un observer activo en este contenedor, lo creamos
        if (!observer && tableContainer) {
            observer = new MutationObserver(function() {
                // Pequeño delay para dejar que Alpine termine sus transiciones
                setTimeout(sync, 10);
            });
            observer.observe(tableContainer, { 
                attributes: true, 
                childList: true, 
                subtree: true,
                attributeFilter: ['class', 'style', 'hidden']
            });
        }
    }

    window.__prospectsStartMailing = function () {
        var tableContainer = document.querySelector('[x-data^="filamentTable"]');
        if (!tableContainer) return;

        // 1. Acceder al componente Alpine de la tabla para un Mirror perfecto
        if (window.Alpine) {
            try {
                var alpineTable = Alpine.$data(tableContainer);
                if (alpineTable && typeof alpineTable.mountAction === 'function') {
                    console.log('[FAB] Lanzando Bulk Action "execute_campaign" vía Alpine Mirror (Sync automático)');
                    
                    // Replicamos el comportamiento exacto del dropdown nativo (visto en el DOM real)
                    // mountAction(nombre, argumentos, contexto)
                    alpineTable.mountAction('execute_campaign', {}, { table: true, bulk: true });
                    
                    // UX: Ocultar botón inmediatamente
                    if (fab) fab.style.display = 'none';
                    return;
                }
            } catch (e) {
                console.warn('[FAB] Error al intentar usar Alpine hook:', e);
            }
        }

        // 2. Fallback: Si el bridge de Alpine falla, usamos el método directo de Livewire (aunque es menos seguro para sync)
        var el = tableContainer.closest('[wire\\:id]');
        if (!el) el = tableContainer;

        var component = window.Livewire && window.Livewire.find(el.getAttribute('wire:id'));
        if (component) {
            console.log('[FAB] Fallback a Livewire call directo');
            component.call('mountTableBulkAction', 'execute_campaign');
            
            if (fab) fab.style.display = 'none';
        }
    };

    // Listeners de apoyo para cuando Livewire refresca el DOM (y perdemos el observer)
    document.addEventListener('livewire:update', function() {
        if (observer) {
            observer.disconnect();
            observer = null;
        }
        setTimeout(sync, 100);
    });
    
    document.addEventListener('livewire:navigated', function() {
        setTimeout(sync, 500);
    });

    // Ejecución inicial y repetitiva por si el DOM tarda
    sync();
    setTimeout(sync, 500);
    
    // Heartbeat de 1 segundo como MÁXIMA seguridad (no es costoso solo mirar 1 selector)
    setInterval(sync, 1000);
})();
</script>

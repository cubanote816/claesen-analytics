<x-filament-panels::page>

    {{--
        Los estilos van acá y no en `resources/css/filament/admin/theme.css` a propósito: el panel de
        **Bertels no registra un tema propio**, así que ese CSS no se carga ahí — y el de Bertels es
        el panel que el cliente usa. Comprobado antes de escribir esto: las utilidades de Tailwind
        tampoco están compiladas (`.fixed{`, `.bottom-0{` no existen en el CSS de Filament), así que
        una barra fija con clases utilitarias no se posicionaría.
    --}}
    <style>
        .claesen-unsaved-bar {
            position: fixed;
            inset-inline: 0;
            bottom: 0;
            z-index: 30;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.75rem 1.5rem;
            background: rgba(255, 255, 255, 0.97);
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.06);
        }

        /* The bar is fixed, so it takes no room in the page: without this spacer the last field
           can never be scrolled above it (measured at 390px: the bottom input sat under the bar). */
        .claesen-unsaved-bar-spacer {
            height: 5rem;
        }

        .claesen-unsaved-bar-text {
            font-size: 0.875rem;
            color: #4b5563;
        }

        .claesen-unsaved-bar-actions {
            display: flex;
            gap: 0.75rem;
        }

        /* Filament alterna el modo oscuro con la clase `.dark` en el documento, no con
           `prefers-color-scheme`, así que la barra se oscurece con esa misma señal. */
        .dark .claesen-unsaved-bar {
            background: rgba(17, 24, 39, 0.97);
            border-top-color: rgba(255, 255, 255, 0.1);
        }

        .dark .claesen-unsaved-bar-text {
            color: #d1d5db;
        }
    </style>

    {{--
        `@input.debounce` no es decoración: en Filament `wire:model` es **diferido**, así que
        escribir no habla con el servidor y la barra recién aparecería en el próximo round-trip
        (medido: no aparecía al escribir). `$refresh()` manda el estado actual —diferido incluido— y
        el servidor vuelve a decidir con `hasUnsavedChanges()`, que es la única fuente de verdad.
        El debounce evita una petición por tecla.
    --}}
    <form wire:submit.prevent="save" x-data @input.debounce.700ms="$wire.$refresh()">
        {{ $this->form }}
    </form>

    {{--
        Barra de cambios sin guardar (WU-S3 del rediseño del backoffice).

        Sustituye al botón «Save» que estaba al final del formulario, como pide el prototipo: la
        acción de guardar aparece **cuando hay algo que guardar**, y desaparece cuando no.

        `hasUnsavedChanges()` compara lo que se guardaría contra lo que hay en la base, no el estado
        crudo del formulario — el `Repeater` de horarios indexa sus filas por uuid y cambiaría de
        «sucio» en cada render. Ver el trait, donde el mapeo es el mismo que usa el guardado.
    --}}
    @if ($this->hasUnsavedChanges())
        <div class="claesen-unsaved-bar-spacer" aria-hidden="true"></div>

        <div class="claesen-unsaved-bar" role="status">
            <span class="claesen-unsaved-bar-text">
                {{ __('website.settings_bar.unsaved') }}
            </span>

            <span class="claesen-unsaved-bar-actions">
                <x-filament::button color="gray" type="button" wire:click="discard">
                    {{ __('website.settings_bar.discard') }}
                </x-filament::button>

                <x-filament::button type="button" wire:click="save">
                    {{ __('website.settings_bar.save_draft') }}
                </x-filament::button>
            </span>
        </div>
    @endif
</x-filament-panels::page>

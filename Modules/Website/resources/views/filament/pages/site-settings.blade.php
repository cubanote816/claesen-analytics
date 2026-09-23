<x-filament-panels::page>
    <form wire:submit.prevent="save">
        {{ $this->form }}

        <div class="mt-6 flex justify-end">
            {{ $this->getFormActions()[0] ?? '' }}
        </div>
    </form>
</x-filament-panels::page>

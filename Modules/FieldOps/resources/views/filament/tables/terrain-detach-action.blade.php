@php
    /** @var int|string $terrainId */
    /** @var bool $canDetach */
@endphp

@if($canDetach)
    <x-filament::button
        type="button"
        color="danger"
        size="sm"
        icon="heroicon-m-link-slash"
        wire:click.prevent="detachTerrain({{ $terrainId }})"
    >
        {{ __('fieldops::resource.terrains.actions.detach') }}
    </x-filament::button>
@else
    <x-filament::button
        type="button"
        color="gray"
        size="sm"
        icon="heroicon-m-link-slash"
        disabled
    >
        {{ __('fieldops::resource.terrains.actions.detach') }}
    </x-filament::button>
@endif

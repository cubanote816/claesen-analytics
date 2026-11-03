@php
    $site = \Modules\Core\Models\Site::forPanel();
    $organization = $site?->organization;
    $panelId = filament()->getId();
    // The panel identity is available even before the local Bertels site is provisioned.
    $displayName = $organization?->name ?? ($panelId === 'bertels' ? filament()->getBrandName() : null);
    $canSwitch = auth()->user()?->hasRole('super_admin') ?? false;
    $targetPanel = $panelId === 'bertels' ? 'admin' : 'bertels';
@endphp

@if ($displayName)
    @if ($canSwitch)
        {{-- The switch remains an audited, full-page navigation; only super_admin sees the control. --}}
        <x-filament::dropdown placement="bottom-end" :teleport="true" data-organization-selector="{{ $panelId }}">
            <x-slot name="trigger">
                <button type="button" class="fi-organization-selector-trigger"
                    aria-label="{{ __('navigation.organization_selector_label', ['organization' => $displayName]) }}">
                    <x-filament::badge
                        :color="$site?->id === \Modules\Core\Models\Site::claesenId() ? 'gray' : 'primary'"
                        icon="heroicon-m-building-office-2"
                        :data-organization-indicator="$organization?->slug"
                        title="{{ __('navigation.organization_indicator_hint') }}"
                    >
                        {{ $displayName }}
                    </x-filament::badge>
                    <x-filament::icon icon="heroicon-m-chevron-down" style="width: 1rem; height: 1rem;" />
                </button>
            </x-slot>

            <x-filament::dropdown.list>
                <x-filament::dropdown.list.item
                    tag="a"
                    :href="route('core.switch-panel', ['panel' => $targetPanel, 'from' => $panelId])"
                    :spa-mode="false"
                    icon="heroicon-m-building-office-2"
                >
                    {{ $targetPanel === 'admin' ? __('navigation.switch_to_claesen') : __('navigation.switch_to_bertels') }}
                </x-filament::dropdown.list.item>
            </x-filament::dropdown.list>
        </x-filament::dropdown>
        <style>
            .fi-organization-selector-trigger {
                display: inline-flex;
                align-items: center;
                gap: 0.25rem;
                border-radius: 0.5rem;
                cursor: pointer;
            }

            .fi-organization-selector-trigger:focus-visible {
                outline: 2px solid currentColor;
                outline-offset: 2px;
            }
        </style>
    @else
        {{-- Other users see the current company, never a link to a panel they cannot enter. --}}
        <x-filament::badge
            :color="$site?->id === \Modules\Core\Models\Site::claesenId() ? 'gray' : 'primary'"
            icon="heroicon-m-building-office-2"
            :data-organization-indicator="$organization?->slug"
            title="{{ __('navigation.organization_indicator_hint') }}"
        >
            {{ $displayName }}
        </x-filament::badge>
    @endif
@endif

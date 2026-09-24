@php
    $site = \Modules\Core\Models\Site::forPanel();
    $organization = $site?->organization;
@endphp

@if ($organization)
    {{-- CLA-600: which company this panel is working on. Filament's own badge on purpose:
         the Bertels panel has no custom Vite theme, so arbitrary Tailwind classes would not compile there. --}}
    <x-filament::badge
        :color="$site->id === \Modules\Core\Models\Site::claesenId() ? 'gray' : 'primary'"
        icon="heroicon-m-building-office-2"
        data-organization-indicator="{{ $organization->slug }}"
        title="{{ __('navigation.organization_indicator_hint') }}"
    >
        {{ $organization->name }}
    </x-filament::badge>
@endif

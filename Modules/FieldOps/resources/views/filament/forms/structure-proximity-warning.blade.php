@php
    /**
     * @var array{proximityMatch?: array{id: int, label: string, distance_meters: float, radius_meters: int}} $data
     */
    $data = $getViewData();
    $match = $data['proximityMatch'] ?? null;
@endphp

@if($match)
    @once
        <style>
            .fieldops-structure-proximity-warning {
                border: 1px solid rgb(254 215 170);
                border-radius: 1rem;
                background: linear-gradient(135deg, rgb(255 251 235) 0%, rgb(255 255 255) 52%, rgb(254 243 199) 100%);
                color: rgb(15 23 42);
                box-shadow: 0 10px 28px rgba(251, 191, 36, 0.1);
                scroll-margin-top: 7rem;
            }

            .dark .fieldops-structure-proximity-warning {
                border-color: rgba(34, 211, 238, 0.28);
                background:
                    radial-gradient(circle at top left, rgba(14, 165, 233, 0.28), transparent 28%),
                    linear-gradient(180deg, rgb(15 23 42) 0%, rgb(2 6 23) 100%);
                color: rgb(248 250 252);
                box-shadow: 0 18px 40px rgba(0, 0, 0, 0.68);
            }

            .fieldops-structure-proximity-warning__icon {
                color: rgb(120 53 15);
            }

            .dark .fieldops-structure-proximity-warning__icon {
                background: rgba(120, 53, 15, 0.45);
                color: rgb(207 250 254);
                box-shadow: 0 0 0 1px rgba(103, 232, 249, 0.24);
            }

            .fieldops-structure-proximity-warning__title {
                color: rgb(69 26 3);
                font-size: 0.875rem;
                font-weight: 800;
                letter-spacing: 0.04em;
                line-height: 1.25rem;
            }

            .dark .fieldops-structure-proximity-warning__title {
                color: rgb(207 250 254);
            }

            .fieldops-structure-proximity-warning__body {
                color: rgb(120 53 15);
                font-size: 0.875rem;
                line-height: 1.5rem;
            }

            .dark .fieldops-structure-proximity-warning__body {
                color: rgb(226 232 240);
            }

            .fieldops-structure-proximity-warning__chip {
                border: 1px solid rgba(217, 119, 6, 0.28);
                background: rgb(254 243 199);
                color: rgb(69 26 3);
            }

            .dark .fieldops-structure-proximity-warning__chip {
                border-color: rgba(103, 232, 249, 0.24);
                background: rgba(34, 211, 238, 0.14);
                color: rgb(236 254 255);
            }

            .fieldops-structure-proximity-warning__pill {
                border: 1px solid rgb(226 232 240);
                background: rgb(255 255 255);
                color: rgb(51 65 85);
            }

            .dark .fieldops-structure-proximity-warning__pill {
                border-color: rgba(148, 163, 184, 0.7);
                background: rgba(15, 23, 42, 0.95);
                color: rgb(248 250 252);
            }
        </style>
    @endonce

    <div
        class="fieldops-structure-proximity-warning p-4"
        x-data
        x-init="
            window.addEventListener('fieldops-structure-proximity-warning-shown', () => {
                requestAnimationFrame(() => {
                    $el.scrollIntoView({ behavior: 'smooth', block: 'start' })
                })
            }, { once: true })
        "
    >
        <div class="flex gap-4">
            <div class="fieldops-structure-proximity-warning__icon flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-amber-200 text-amber-950 ring-1 ring-amber-300/80">
                <x-heroicon-o-exclamation-triangle class="h-6 w-6" />
            </div>

            <div class="min-w-0 flex-1">
                <div class="fieldops-structure-proximity-warning__title">
                    {{ __('fieldops::resource.structures.validation.proximity_warning_title') }}
                </div>

                <p class="fieldops-structure-proximity-warning__body mt-1">
                    {{ __('fieldops::resource.structures.validation.proximity_warning_body', [
                        'distance' => number_format((float) $match['distance_meters'], 1, ',', '.'),
                        'radius' => $match['radius_meters'],
                    ]) }}
                </p>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <div class="fieldops-structure-proximity-warning__chip rounded-full px-3 py-1 text-xs font-semibold">
                        {{ $match['label'] }}
                    </div>
                    <div class="fieldops-structure-proximity-warning__pill rounded-full px-3 py-1 text-xs font-semibold">
                        {{ number_format((float) $match['distance_meters'], 1, ',', '.') }} m
                    </div>
                    <div class="fieldops-structure-proximity-warning__pill rounded-full px-3 py-1 text-xs font-semibold">
                        {{ __('fieldops::resource.structures.validation.proximity_within_radius', ['radius' => $match['radius_meters']]) }}
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <x-filament::button
                        tag="a"
                        :href="\Modules\FieldOps\Filament\Resources\StructureResource::getUrl('view', ['record' => $match['id']])"
                        target="_blank"
                        color="warning"
                        size="sm"
                        icon="heroicon-m-arrow-top-right-on-square"
                    >
                        {{ __('fieldops::resource.structures.validation.proximity_review') }}
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        color="warning"
                        size="sm"
                        icon="heroicon-m-link"
                        wire:click="attachToDetectedStructure({{ $match['id'] }})"
                    >
                        {{ __('fieldops::resource.structures.validation.proximity_attach') }}
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        color="warning"
                        size="sm"
                        icon="heroicon-m-check"
                        wire:click="createAnyway({{ $match['id'] }})"
                    >
                        {{ __('fieldops::resource.structures.validation.proximity_create_anyway') }}
                    </x-filament::button>
                </div>
            </div>
        </div>
    </div>
@endif

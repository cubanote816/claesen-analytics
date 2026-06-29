@php
    /** @var \Modules\Cafca\Models\Employee $employee */
    $employee = $getState();
    $isNl     = app()->getLocale() === 'nl';

    $burnout      = $employee->insight?->burnout_risk_score;
    $archetype    = $employee->insight?->archetype_label;
    $archetypeIcon = $employee->insight?->archetype_icon;

    $statusColor = $employee->fl_active
        ? ['bg' => 'rgba(165,214,16,.12)', 'border' => 'rgba(165,214,16,.25)', 'text' => '#a5d610', 'dot' => '#a5d610']
        : ['bg' => 'rgba(230,0,126,.12)',  'border' => 'rgba(230,0,126,.25)',  'text' => '#e6007e', 'dot' => '#e6007e'];

    $statusLabel = $employee->fl_active
        ? ($isNl ? 'Actief' : 'Active')
        : ($isNl ? 'Inactief' : 'Inactive');
@endphp

<div class="emp-profile-hero relative overflow-hidden rounded-2xl"
     style="background: linear-gradient(135deg, rgba(255,255,255,.035) 0%, rgba(255,255,255,.015) 100%);
            border: 1px solid rgba(255,255,255,.07);
            box-shadow: 0 1px 0 0 rgba(255,255,255,.04) inset, 0 20px 60px -20px rgba(0,0,0,.5);">

    {{-- Top accent line --}}
    <div class="absolute inset-x-0 top-0 h-px"
         style="background: linear-gradient(90deg, transparent 0%, rgba(0,174,239,.5) 40%, rgba(0,174,239,.5) 60%, transparent 100%)"></div>

    <div class="px-6 py-5 lg:px-8 lg:py-6">
        <div class="flex items-start gap-5 lg:gap-6">

            {{-- Avatar --}}
            <div class="shrink-0 relative">
                <img src="{{ $employee->avatar_url }}"
                     alt="{{ $employee->name }}"
                     class="w-[68px] h-[68px] lg:w-20 lg:h-20 rounded-xl object-cover"
                     style="box-shadow: 0 0 0 1px rgba(255,255,255,.1), 0 8px 24px rgba(0,0,0,.4)" />
                {{-- Active indicator dot --}}
                <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2"
                      style="background-color: {{ $statusColor['dot'] }}; border-color: #14141b"></span>
            </div>

            {{-- Identity + contacts --}}
            <div class="flex-1 min-w-0">

                {{-- Name row --}}
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="text-xl lg:text-2xl font-bold text-white tracking-tight leading-tight">
                            {{ $employee->name }}
                        </h2>
                        <p class="mt-0.5 text-sm font-medium" style="color: #6c757d">
                            {{ $employee->function ?: ($isNl ? 'Geen functie' : 'No function specified') }}
                        </p>
                    </div>

                    {{-- Status badge --}}
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold tracking-widest uppercase shrink-0"
                          style="background: {{ $statusColor['bg'] }}; border: 1px solid {{ $statusColor['border'] }}; color: {{ $statusColor['text'] }}">
                        <span class="w-1.5 h-1.5 rounded-full" style="background: {{ $statusColor['dot'] }}; {{ $employee->fl_active ? 'animation: pulse 2s infinite' : '' }}"></span>
                        {{ $statusLabel }}
                    </span>
                </div>

                {{-- Contact strip --}}
                <div class="mt-3.5 flex flex-wrap items-center gap-x-5 gap-y-1.5">
                    @if($employee->mobile)
                        <div class="flex items-center gap-1.5">
                            <x-heroicon-m-phone class="w-3.5 h-3.5 shrink-0" style="color: #00aeef" />
                            <span class="text-sm" style="color: #adb5bd">{{ $employee->mobile }}</span>
                        </div>
                    @endif
                    @if($employee->email)
                        <div class="flex items-center gap-1.5">
                            <x-heroicon-m-envelope class="w-3.5 h-3.5 shrink-0" style="color: #00aeef" />
                            <span class="text-sm truncate" style="color: #adb5bd; max-width: 220px">{{ $employee->email }}</span>
                        </div>
                    @endif
                    @if($employee->full_address)
                        <div class="flex items-center gap-1.5">
                            <x-heroicon-m-map-pin class="w-3.5 h-3.5 shrink-0" style="color: #00aeef" />
                            <span class="text-sm" style="color: #adb5bd">{{ $employee->full_address }}</span>
                        </div>
                    @endif
                </div>

            </div>
        </div>
    </div>
</div>

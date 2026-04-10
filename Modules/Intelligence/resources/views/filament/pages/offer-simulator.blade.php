<x-filament-panels::page>
    <div class="relative group mb-8">
        <div class="absolute -inset-1 bg-gradient-to-r from-amber-500/20 to-orange-500/20 rounded-3xl blur opacity-25 group-hover:opacity-40 transition duration-1000"></div>
        <div class="relative px-8 py-4 bg-white dark:bg-gray-950 border border-amber-500/20 rounded-3xl flex items-center gap-6 shadow-xl">
            <div class="flex-shrink-0 w-12 h-12 rounded-2xl bg-amber-500/10 flex items-center justify-center border border-amber-500/20">
                <x-heroicon-o-beaker class="w-6 h-6 text-amber-500 animate-pulse" />
            </div>
            <div class="flex-grow">
                <p class="text-[10px] font-black text-amber-500 uppercase tracking-widest mb-0.5">{{ app()->getLocale() === 'nl' ? 'PROTOTYPE (DEMO MODUS)' : 'PROTOTYPE (DEMO MODE)' }}</p>
                <h4 class="text-gray-900 dark:text-gray-300 font-bold text-sm leading-tight tracking-tight">
                    {{ app()->getLocale() === 'nl' 
                        ? 'Deze simulator is in ontwikkeling. Alle resultaten zijn AI-ramingen en MOETEN worden gecontroleerd door een technisch expert van Claesen.' 
                        : 'This simulator is in development. All results are AI estimations and MUST be verified by a Claesen technical expert.' }}
                </h4>
            </div>
        </div>
    </div>

    <form wire:submit.prevent="simulate">
        {{ $this->form }}

        <div class="mt-4 flex items-center gap-x-3">
            {{ $this->getFormActions()[0] }}
        </div>
    </form>

    @if ($results)
        <div class="mt-8 animate-fade-in space-y-12" x-data="{ activeTab: 'finance' }">
            
            @if($results['is_fallback'] ?? false)
                <div class="relative group">
                    <div class="absolute -inset-1 bg-gradient-to-r from-red-600 to-rose-600 rounded-3xl blur opacity-25 group-hover:opacity-40 transition duration-1000 group-hover:duration-200"></div>
                    <div class="relative px-8 py-4 bg-red-50 dark:bg-red-950/20 border border-red-500/20 rounded-3xl flex items-center gap-6 shadow-2xl">
                        <div class="flex-shrink-0 w-12 h-12 rounded-2xl bg-red-500/10 flex items-center justify-center border border-red-500/20">
                            <x-heroicon-o-signal-slash class="w-6 h-6 text-red-500" />
                        </div>
                        <div class="flex-grow">
                            <p class="text-[9px] font-black text-red-500 uppercase tracking-widest mb-0.5">{{ app()->getLocale() === 'nl' ? 'OFFLINE MODUS (FALLBACK)' : 'OFFLINE MODE (FALLBACK)' }}</p>
                            <h4 class="text-red-900 dark:text-red-200 font-bold text-sm leading-tight tracking-tight">
                                {{ app()->getLocale() === 'nl' 
                                    ? 'Geen AI-verbinding. Deze schatting is gebaseerd op gemiddelde historische data (MAMO).' 
                                    : 'No AI connection. This estimate is based on average historical data (MAMO).' }}
                            </h4>
                        </div>
                    </div>
                </div>
            @endif
            
            @if($results['is_gibberish'] ?? false)
                <div class="relative group">
                    <div class="absolute -inset-1 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-3xl blur opacity-25 group-hover:opacity-40 transition duration-1000 group-hover:duration-200"></div>
                    <div class="relative px-8 py-6 bg-white dark:bg-gray-900 border border-indigo-500/20 rounded-3xl flex items-center gap-6 shadow-2xl overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-500/5 rounded-full -mr-16 -mt-16 blur-3xl"></div>
                        <div class="flex-shrink-0 w-14 h-14 rounded-2xl bg-indigo-500/10 flex items-center justify-center border border-indigo-500/20">
                            <x-heroicon-o-academic-cap class="w-8 h-8 text-indigo-500" />
                        </div>
                        <div class="flex-grow">
                            <p class="text-[10px] font-black text-indigo-500 uppercase tracking-widest mb-1">{{ app()->getLocale() === 'nl' ? 'LOGICA CHECK FAAL' : 'LOGIC CHECK FAILED' }}</p>
                            <h4 class="text-gray-900 dark:text-white font-bold text-lg leading-tight tracking-tight italic">
                                "{{ $results['missing_info_request'] ?? '' }}"
                            </h4>
                        </div>
                    </div>
                </div>
            @endif

            @if($results['is_incomplete'] ?? false)
                <div class="relative group">
                    <div class="absolute -inset-1 bg-gradient-to-r from-amber-500 to-orange-600 rounded-3xl blur opacity-25 group-hover:opacity-40 transition duration-1000 group-hover:duration-200"></div>
                    <div class="relative px-8 py-6 bg-white dark:bg-gray-900 border border-amber-500/20 rounded-3xl flex items-center gap-6 shadow-2xl">
                        <div class="flex-shrink-0 w-14 h-14 rounded-2xl bg-amber-500/10 flex items-center justify-center border border-amber-500/20">
                            <x-heroicon-o-chat-bubble-left-right class="w-8 h-8 text-amber-500" />
                        </div>
                        <div class="flex-grow">
                            <p class="text-[10px] font-black text-amber-500 uppercase tracking-widest mb-1">{{ app()->getLocale() === 'nl' ? 'EXTRA INFORMATIE NODIG' : 'MORE INFORMATION NEEDED' }}</p>
                            <h4 class="text-gray-900 dark:text-white font-bold text-lg leading-tight tracking-tight">
                                {{ $results['missing_info_request'] ?? '' }}
                            </h4>
                        </div>
                    </div>
                </div>
            @endif

            @if(!empty($results['assumptions_made']))
                <div class="relative group">
                    <div class="absolute -inset-1 bg-gradient-to-r from-blue-500 to-cyan-600 rounded-3xl blur opacity-10 group-hover:opacity-25 transition duration-1000"></div>
                    <div class="relative px-8 py-5 bg-blue-50/50 dark:bg-blue-950/20 border border-blue-500/20 rounded-3xl flex items-start gap-6 shadow-xl backdrop-blur-sm">
                        <div class="flex-shrink-0 mt-1">
                            <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center border border-blue-500/20">
                                <x-heroicon-o-information-circle class="w-6 h-6 text-blue-500" />
                            </div>
                        </div>
                        <div class="flex-grow">
                            <p class="text-[9px] font-black text-blue-500 uppercase tracking-widest mb-2">{{ app()->getLocale() === 'nl' ? 'AI TECHNISCHE AANNAMES' : 'AI TECHNICAL ASSUMPTIONS' }}</p>
                            <div class="text-gray-700 dark:text-blue-200 text-xs leading-relaxed prose prose-sm dark:prose-invert max-w-none prose-p:my-1 prose-ul:my-1">
                                {!! Str::markdown($results['assumptions_made'] ?? '') !!}
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Premium Tab Navigation -->
            <div class="flex p-1 mb-8 space-x-1 bg-white dark:bg-gray-900/50 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-2xl max-w-2xl mx-auto shadow-2xl">
                <button 
                    @click="activeTab = 'finance'"
                    :class="{ 'bg-primary-500/10 dark:bg-primary-500/20 text-primary-600 dark:text-primary-400 border-primary-500/30': activeTab === 'finance', 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white': activeTab !== 'finance' }"
                    class="flex-1 px-4 py-2.5 text-sm font-bold uppercase tracking-widest rounded-xl transition-all duration-300 border border-transparent outline-none">
                    {{ app()->getLocale() === 'nl' ? 'Financiën' : 'Finance' }}
                </button>
                <button 
                    @click="activeTab = 'swot'"
                    :class="{ 'bg-primary-500/10 dark:bg-primary-500/20 text-primary-600 dark:text-primary-400 border-primary-500/30': activeTab === 'swot', 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white': activeTab !== 'swot' }"
                    class="flex-1 px-4 py-2.5 text-sm font-bold uppercase tracking-widest rounded-xl transition-all duration-300 border border-transparent outline-none">
                    {{ app()->getLocale() === 'nl' ? 'Strategisch DAFO' : 'Strategic SWOT' }}
                </button>
                <button 
                    @click="activeTab = 'came'"
                    :class="{ 'bg-primary-500/10 dark:bg-primary-500/20 text-primary-600 dark:text-primary-400 border-primary-500/30': activeTab === 'came', 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white': activeTab !== 'came' }"
                    class="flex-1 px-4 py-2.5 text-sm font-bold uppercase tracking-widest rounded-xl transition-all duration-300 border border-transparent outline-none">
                    {{ app()->getLocale() === 'nl' ? 'Lessen (CAME)' : 'Lessons (CAME)' }}
                </button>
            </div>

            <!-- Tab Content: Finance -->
            <div x-show="activeTab === 'finance'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="space-y-8">
                <x-filament::section>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                        <!-- Left: Main Estimate -->
                        <div class="lg:col-span-1 space-y-6">
                            <div class="p-8 rounded-3xl bg-gray-950 border border-gray-800 shadow-2xl relative overflow-hidden group">
                                <div class="absolute -top-10 -right-10 w-40 h-40 bg-primary-500/5 rounded-full blur-3xl group-hover:bg-primary-500/10 transition-colors"></div>
                                <div class="relative z-10">
                                    <div class="flex justify-between items-center mb-8">
                                        <span class="text-gray-400 text-[10px] font-black uppercase tracking-[0.2em]">Totale Raming (MAMO)</span>
                                        <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-primary-500/10 border border-primary-500/20">
                                            <div class="w-1.5 h-1.5 rounded-full bg-primary-400 animate-pulse"></div>
                                            <span class="text-[9px] font-black text-primary-400 uppercase tracking-tighter">Live AI</span>
                                        </div>
                                    </div>
                                    <div class="text-5xl font-black text-white mb-6 tracking-tighter tabular-nums">
                                        € {{ number_format($results['projected_cost'] ?? 0, 2, ',', '.') }}
                                    </div>
                                    <div class="p-4 rounded-xl bg-primary-500/5 border border-primary-500/10 text-[11px] text-gray-400 leading-relaxed italic">
                                        "Gecalculeerd op basis van historische MAMO-patronen en gecorrigeerd voor inflatie (4% p.j.)."
                                    </div>
                                </div>
                            </div>

                             <!-- Historical Reference Cards -->
                            <div class="space-y-3">
                                <h4 class="text-[10px] font-black text-gray-400 dark:text-gray-600 uppercase tracking-widest pl-2 flex items-center gap-2">
                                    <x-heroicon-o-magnifying-glass-circle class="w-4 h-4" />
                                    {{ app()->getLocale() === 'nl' ? 'Geraadpleegde CAFCA Projecten' : 'Consulted CAFCA Projects' }}
                                </h4>
                                <div class="grid grid-cols-1 gap-2">
                                    @foreach($results['historical_references'] ?? [] as $ref)
                                        <div 
                                            class="group relative flex items-center justify-between p-3 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 hover:border-blue-500/50 hover:shadow-lg hover:shadow-blue-500/5 transition-all duration-300 cursor-pointer overflow-hidden"
                                            title="Bekijk details van project {{ $ref['id'] }}"
                                        >
                                            <div class="absolute inset-0 bg-blue-500/[0.02] opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                            <div class="flex items-center gap-3 relative z-10">
                                                <div class="w-8 h-8 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-[10px] font-black text-gray-400 dark:text-gray-500 group-hover:bg-blue-500/10 group-hover:text-blue-500 transition-colors">
                                                    {{ substr($ref['id'], -2) }}
                                                </div>
                                                <div class="flex flex-col">
                                                    <span class="text-xs font-bold text-gray-900 dark:text-gray-300 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors truncate max-w-[120px]">
                                                        {{ $ref['name'] }}
                                                    </span>
                                                    <span class="text-[9px] text-gray-400 dark:text-gray-500 font-medium uppercase tracking-tighter">
                                                        ID: {{ $ref['id'] }} • {{ $ref['city'] ?? 'België' }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="flex flex-col items-end relative z-10">
                                                <span class="text-[10px] font-black text-gray-400 dark:text-gray-600 group-hover:text-blue-500 transition-colors tracking-tighter">{{ $ref['year'] }}</span>
                                                <x-heroicon-s-chevron-right class="w-3 h-3 text-gray-300 dark:text-gray-700 opacity-0 group-hover:opacity-100 group-hover:translate-x-1 transition-all" />
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <!-- Right: MAMO Grid (CAFCA Style) -->
                        <div class="lg:col-span-2 space-y-6">
                            <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-widest mb-2 flex items-center gap-2">
                                <div class="w-1.5 h-4 bg-primary-500 rounded-full"></div>
                                {{ app()->getLocale() === 'nl' ? 'Kostenstructuur (MAMO Grid)' : 'Cost Structure (MAMO Grid)' }}
                            </h3>
                            
                            <div class="grid grid-cols-2 gap-4">
                                @php
                                    $mamoIcons = [
                                        'MATERIAAL (M)' => 'heroicon-o-cube',
                                        'ARBEID (A)' => 'heroicon-o-user-group',
                                        'MATERIEEL (M)' => 'heroicon-o-truck',
                                        'ONDERAANNEMING (O)' => 'heroicon-o-identification',
                                    ];
                                @endphp
                                @foreach ($results['breakdown'] ?? [] as $key => $value)
                                    <div class="p-6 rounded-3xl bg-white dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 flex flex-col justify-between group hover:bg-primary-500/[0.02] hover:border-primary-500/30 transition-all duration-500 shadow-sm dark:shadow-none">
                                        <div class="flex items-center justify-between mb-4">
                                            <div class="p-2.5 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-400 dark:text-gray-500 group-hover:text-primary-600 dark:group-hover:text-primary-400 group-hover:border-primary-500/20 transition-all">
                                                @if(isset($mamoIcons[$key]))
                                                    @svg($mamoIcons[$key], 'w-5 h-5')
                                                @endif
                                            </div>
                                            <span class="text-[10px] font-black text-gray-400 dark:text-gray-600 uppercase tracking-widest text-right">{{ explode(' ', $key)[0] }}</span>
                                        </div>
                                        <div class="space-y-1">
                                            <div class="text-2xl font-black text-gray-900 dark:text-white tabular-nums tracking-tight">
                                                € {{ number_format($value, 2, ',', '.') }}
                                            </div>
                                            <div class="w-full bg-gray-100 dark:bg-gray-800 h-1 rounded-full overflow-hidden">
                                                <div class="bg-primary-500 h-full rounded-full opacity-60 dark:opacity-30" style="width: {{ ($results['projected_cost'] ?? 0) > 0 ? ($value / ($results['projected_cost'] ?? 0)) * 100 : 0 }}%;"></div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- CAFCA Calculation Helper (MAMO Setup) -->
                            <div class="mb-6 p-6 rounded-3xl bg-primary-600 border border-primary-500 shadow-2xl relative overflow-hidden group">
                                <div class="absolute -top-10 -left-10 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
                                <div class="relative z-10">
                                    <div class="flex items-center gap-2 mb-4">
                                        <x-heroicon-s-calculator class="w-4 h-4 text-white" />
                                        <span class="text-[10px] font-black text-white/80 uppercase tracking-widest">CAFCA Calculatie Helper</span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-x-6 gap-y-4">
                                        @php
                                            $mamoLabels = app()->getLocale() === 'nl' 
                                                ? ['Materiaal (M)' => 'M', 'Arbeid (A)' => 'A', 'Materieel (E)' => 'E', 'Onderaann. (S)' => 'S'] 
                                                : ['Material (M)' => 'M', 'Labor (A)' => 'A', 'Equipment (E)' => 'E', 'Subcontract. (S)' => 'S'];
                                        @endphp
                                        @foreach($mamoLabels as $label => $key)
                                            <div class="flex justify-between items-center border-b border-white/20 pb-1">
                                                <span class="text-[10px] font-bold text-white/70">{{ $label }}</span>
                                                <span class="text-lg font-black text-white tabular-nums">+{{ $results['mamo_summary'][$key] ?? 0 }}%</span>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="mt-4 text-[9px] text-white/60 font-medium italic">
                                        @if(app()->getLocale() === 'nl')
                                            "Voer deze percentages in de 'Calculatie' tab van CAFCA in om de winsten te synchroniseren."
                                        @else
                                            "Enter these percentages into the 'Calculatie' tab in CAFCA to synchronize profit margins."
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Mini AI Insight Bar -->
                            <div class="p-5 rounded-2xl bg-primary-500/[0.03] dark:bg-primary-500/5 border border-primary-500/10 flex items-start gap-4 shadow-inner">
                                <div class="mt-1 bg-primary-500/10 p-2 rounded-lg">
                                    <x-heroicon-s-sparkles class="w-4 h-4 text-primary-600 dark:text-primary-400" />
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed italic">
                                    "{{ $results['ai_insights'] ?? '' }}"
                                </div>
                            </div>
                        </div>
                    </div>
                </x-filament::section>

                @if(count($results['budget_sections'] ?? []) > 0)
                    <x-filament::section collapsible header-text="{{ app()->getLocale() === 'nl' ? 'Offertelijnen (CAFCA Inhoud)' : 'Offer Lines (CAFCA Inhoud)' }}" icon="heroicon-o-list-bullet">
                        <div class="overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-800 shadow-2xl">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 bg-white dark:bg-gray-950/80">
                                <thead>
                                    <tr class="bg-gray-50 dark:bg-gray-900 text-left text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-600">
                                        <th class="px-6 py-5">Ref / ID</th>
                                        <th class="px-6 py-5">{{ app()->getLocale() === 'nl' ? 'Omschrijving (Lijn)' : 'Description (Line)' }}</th>
                                        <th class="px-6 py-5 text-center">{{ app()->getLocale() === 'nl' ? 'Hoev.' : 'Qty.' }}</th>
                                        <th class="px-6 py-5 text-center">{{ app()->getLocale() === 'nl' ? 'Eenh.' : 'Unit' }}</th>
                                        <th class="px-6 py-5 text-center">{{ app()->getLocale() === 'nl' ? 'Arb./Eenh' : 'Lab./Unit' }}</th>
                                        <th class="px-6 py-5 text-right">{{ app()->getLocale() === 'nl' ? 'Eenh.prijs' : 'Unit Price' }}</th>
                                        <th class="px-6 py-5 text-right">{{ app()->getLocale() === 'nl' ? 'Totaalprijs' : 'Line Total' }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-900">
                                    @foreach($results['budget_sections'] as $section)
                                        <tr class="bg-primary-50/50 dark:bg-primary-500/5 backdrop-blur-sm">
                                            <td colspan="7" class="px-6 py-3 text-[11px] font-black text-primary-600 dark:text-primary-400 uppercase tracking-widest border-y border-primary-500/10">
                                                {{ $section['title'] }}
                                            </td>
                                        </tr>
                                        @foreach($section['items'] as $material)
                                            <tr class="hover:bg-primary-500/[0.03] transition-colors cursor-default group">
                                                <td class="px-6 py-5">
                                                    <code @class([
                                                        'px-2 py-1 rounded border text-[10px] font-bold uppercase tracking-tighter transition-colors',
                                                        'bg-emerald-500/10 border-emerald-500/20 text-emerald-500' => $material['source_type'] === 'db_verified',
                                                        'bg-blue-500/10 border-blue-500/20 text-blue-400' => in_array($material['source_type'], ['db_modern_substitute', 'db_ai_estimated']),
                                                        'bg-amber-500/10 border-amber-500/20 text-amber-500' => $material['source_type'] === 'internet',
                                                    ])>
                                                        {{ $material['ref'] }}
                                                    </code>
                                                </td>
                                                <td class="px-6 py-5">
                                                    <div class="text-sm text-gray-900 dark:text-gray-300 font-bold dark:font-medium group-hover:text-primary-600 dark:group-hover:text-white transition-colors">{{ $material['name'] }}</div>
                                                    <div class="text-[9px] text-gray-500 dark:text-gray-600 italic mt-1">{{ $material['reason'] }}</div>
                                                </td>
                                                <td class="px-6 py-5 text-center text-sm font-black text-gray-700 dark:text-gray-300 tabular-nums">{{ $material['quantity'] }}</td>
                                                <td class="px-6 py-5 text-center text-[10px] text-gray-400 dark:text-gray-500 font-bold uppercase">{{ $material['unit'] }}</td>
                                                <td class="px-6 py-5 text-center">
                                                    <span class="px-2 py-0.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-[11px] font-black text-gray-600 dark:text-gray-400 tabular-nums">
                                                        {{ number_format($material['arb_per_eenheid'], 2) }} h
                                                    </span>
                                                </td>
                                                <td class="px-6 py-5 text-sm text-right text-gray-600 dark:text-gray-400 tabular-nums font-medium">€ {{ number_format($material['unit_price'], 2, ',', '.') }}</td>
                                                <td class="px-6 py-5 text-sm text-right font-black text-gray-900 dark:text-white tabular-nums group-hover:scale-105 transition-transform origin-right">€ {{ number_format($material['line_total'], 2, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="bg-gray-50/50 dark:bg-gray-900/50 backdrop-blur-md">
                                        <th colspan="6" class="px-6 py-4 text-right text-[10px] font-black uppercase text-gray-400 dark:text-gray-500">{{ app()->getLocale() === 'nl' ? 'Totaal (Excl. BTW):' : 'Total (Excl. VAT):' }}</th>
                                        <td class="px-6 py-4 text-right text-base font-black text-primary-600 dark:text-primary-500 tabular-nums">
                                            € {{ number_format($results['projected_cost'] ?? 0, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </x-filament::section>
                @endif
            </div>

            <!-- Tab Content: SWOT -->
            <div x-show="activeTab === 'swot'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="space-y-12">
                <x-filament::section>
                    <div class="space-y-12">
                        <!-- Premium 2x2 SWOT Matrix -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 relative">
                            <!-- Matrix Divider (Visual Only) -->
                            <div class="absolute inset-0 flex items-center justify-center pointer-events-none opacity-10 hidden md:flex">
                                <div class="w-px h-full bg-gray-400"></div>
                                <div class="h-px w-full bg-gray-400 absolute"></div>
                            </div>

                            <!-- Strengths (Sterktes) -->
                            <div class="p-8 rounded-[2rem] bg-emerald-50/50 dark:bg-emerald-500/5 border border-emerald-500/20 shadow-xl shadow-emerald-500/5 relative overflow-hidden group">
                                <div class="absolute -top-12 -right-12 w-32 h-32 bg-emerald-500/10 rounded-full blur-3xl group-hover:bg-emerald-500/20 transition-all"></div>
                                <div class="flex items-center gap-3 mb-6 relative z-10">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center border border-emerald-500/20">
                                        <x-heroicon-s-check-badge class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
                                    </div>
                                    <h4 class="text-sm font-black text-emerald-800 dark:text-emerald-300 uppercase tracking-widest">{{ app()->getLocale() === 'nl' ? 'STERKTES' : 'STRENGTHS' }}</h4>
                                </div>
                                <ul class="space-y-3 relative z-10">
                                    @foreach($results['swot']['strengths'] ?? [] as $item)
                                        <li class="flex items-start gap-2 text-xs font-bold text-emerald-900/80 dark:text-emerald-200/70 leading-relaxed">
                                            <span class="mt-1 w-1 h-1 rounded-full bg-emerald-500 shrink-0"></span>
                                            {{ $item }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>

                            <!-- Weaknesses (Zwaktes) -->
                            <div class="p-8 rounded-[2rem] bg-amber-50/50 dark:bg-amber-500/5 border border-amber-500/20 shadow-xl shadow-amber-500/5 relative overflow-hidden group">
                                <div class="absolute -top-12 -right-12 w-32 h-32 bg-amber-500/10 rounded-full blur-3xl group-hover:bg-amber-500/20 transition-all"></div>
                                <div class="flex items-center gap-3 mb-6 relative z-10">
                                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center border border-amber-500/20">
                                        <x-heroicon-s-exclamation-triangle class="w-6 h-6 text-amber-600 dark:text-amber-400" />
                                    </div>
                                    <h4 class="text-sm font-black text-amber-800 dark:text-amber-300 uppercase tracking-widest">{{ app()->getLocale() === 'nl' ? 'ZWAKTES' : 'WEAKNESSES' }}</h4>
                                </div>
                                <ul class="space-y-3 relative z-10">
                                    @foreach($results['swot']['weaknesses'] ?? [] as $item)
                                        <li class="flex items-start gap-2 text-xs font-bold text-amber-900/80 dark:text-amber-200/70 leading-relaxed">
                                            <span class="mt-1 w-1 h-1 rounded-full bg-amber-500 shrink-0"></span>
                                            {{ $item }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>

                            <!-- Opportunities (Kansen) -->
                            <div class="p-8 rounded-[2rem] bg-indigo-50/50 dark:bg-indigo-500/5 border border-indigo-500/20 shadow-xl shadow-indigo-500/5 relative overflow-hidden group">
                                <div class="absolute -top-12 -right-12 w-32 h-32 bg-indigo-500/10 rounded-full blur-3xl group-hover:bg-indigo-500/20 transition-all"></div>
                                <div class="flex items-center gap-3 mb-6 relative z-10">
                                    <div class="w-10 h-10 rounded-xl bg-indigo-500/10 flex items-center justify-center border border-indigo-500/20">
                                        <x-heroicon-s-light-bulb class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                                    </div>
                                    <h4 class="text-sm font-black text-indigo-800 dark:text-indigo-300 uppercase tracking-widest">{{ app()->getLocale() === 'nl' ? 'KANSEN' : 'OPPORTUNITIES' }}</h4>
                                </div>
                                <ul class="space-y-3 relative z-10">
                                    @foreach($results['swot']['opportunities'] ?? [] as $item)
                                        <li class="flex items-start gap-2 text-xs font-bold text-indigo-900/80 dark:text-indigo-200/70 leading-relaxed">
                                            <span class="mt-1 w-1 h-1 rounded-full bg-indigo-500 shrink-0"></span>
                                            {{ $item }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>

                            <!-- Threats (Bedreigingen) -->
                            <div class="p-8 rounded-[2rem] bg-rose-50/50 dark:bg-rose-500/5 border border-rose-500/20 shadow-xl shadow-rose-500/5 relative overflow-hidden group">
                                <div class="absolute -top-12 -right-12 w-32 h-32 bg-rose-500/10 rounded-full blur-3xl group-hover:bg-rose-500/20 transition-all"></div>
                                <div class="flex items-center gap-3 mb-6 relative z-10">
                                    <div class="w-10 h-10 rounded-xl bg-rose-500/10 flex items-center justify-center border border-rose-500/20">
                                        <x-heroicon-s-shield-exclamation class="w-6 h-6 text-rose-600 dark:text-rose-400" />
                                    </div>
                                    <h4 class="text-sm font-black text-rose-800 dark:text-rose-300 uppercase tracking-widest">{{ app()->getLocale() === 'nl' ? 'BEDREIGINGEN' : 'THREATS' }}</h4>
                                </div>
                                <ul class="space-y-3 relative z-10">
                                    @foreach($results['swot']['threats'] ?? [] as $item)
                                        <li class="flex items-start gap-2 text-xs font-bold text-rose-900/80 dark:text-rose-200/70 leading-relaxed">
                                            <span class="mt-1 w-1 h-1 rounded-full bg-rose-500 shrink-0"></span>
                                            {{ $item }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-4 mb-8 pl-4 border-l-4 border-primary-500">
                            <h3 class="text-2xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">{{ app()->getLocale() === 'nl' ? 'Strategisch Dossier' : 'Strategic Dossier' }}</h3>
                            <span class="px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-[9px] font-black text-gray-500 uppercase tracking-widest">{{ app()->getLocale() === 'nl' ? 'Diepgaande Analyse' : 'In-depth Analysis' }}</span>
                        </div>
                        
                        <div class="p-10 rounded-[3rem] bg-gray-50 dark:bg-gradient-to-br dark:from-gray-900/40 dark:to-black/20 border border-gray-100 dark:border-gray-800/60 shadow-inner">
                            <div class="text-gray-700 dark:text-gray-400 text-lg leading-[1.8] font-medium tracking-tight">
                                {!! nl2br(e($results['swot_detailed'] ?? '')) !!}
                            </div>
                        </div>
                    </div>
                </x-filament::section>
            </div>

            <!-- Tab Content: CAME -->
            <div x-show="activeTab === 'came'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="space-y-8">
                <x-filament::section>
                    <div class="max-w-4xl mx-auto py-12">
                        <div class="text-center mb-16 relative">
                            <div class="absolute inset-0 flex items-center justify-center opacity-5">
                                <x-heroicon-s-shield-check class="w-64 h-64 text-amber-500" />
                            </div>
                            <div class="relative z-10">
                                <span class="px-4 py-1.5 rounded-full bg-amber-500/10 border border-amber-500/20 text-[10px] font-black text-amber-500 uppercase tracking-[0.3em] mb-4 inline-block">{{ app()->getLocale() === 'nl' ? 'Strategische Shield' : 'Strategic Shield' }}</span>
                                <h3 class="text-5xl font-black text-gray-900 dark:text-white tracking-tighter uppercase mb-4">{{ app()->getLocale() === 'nl' ? 'Lessen uit het Verleden' : 'Lessons from the Past' }}</h3>
                                <p class="text-gray-500 font-medium tracking-widest uppercase text-xs">{{ app()->getLocale() === 'nl' ? 'Methodologie' : 'Methodology' }}: CAME (Correct, Adapt, Maintain, Exploit)</p>
                            </div>
                        </div>

                        <div class="relative">
                            <div class="absolute -inset-4 bg-gradient-to-br from-amber-500/10 to-transparent blur-3xl opacity-20"></div>
                            <div class="p-12 rounded-[3.5rem] bg-white dark:bg-gray-950 border border-amber-500/20 shadow-[0_0_50px_-12px_rgba(245,158,11,0.15)] dark:shadow-none relative overflow-hidden group">
                                <div class="prose prose-xl dark:prose-invert max-w-none">
                                    <div class="text-gray-800 dark:text-gray-200 leading-[1.7] font-medium tracking-tight whitespace-pre-line first-letter:text-6xl first-letter:font-black first-letter:text-amber-500 first-letter:float-left first-letter:mr-4 first-letter:mt-2">
                                        {{ $results['came_strategy'] ?? '' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-filament::section>
            </div>
        </div>
    @endif
</x-filament-panels::page>

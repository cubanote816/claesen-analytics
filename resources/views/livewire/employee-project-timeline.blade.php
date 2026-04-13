<div class="w-full relative space-y-12" x-data="employeeDashboard({
    totalHours: {{ json_encode($this->totalHours) }},
    distributionLabels: {{ json_encode(array_keys($distribution)) }},
    distributionSeries: {{ json_encode(array_values($distribution)) }},
    temporalTitle: {{ json_encode($this->getChartDataProperty()['temporalTitle'] ?? 'Daily Trend') }},
    temporalLabels: {{ json_encode($this->getChartDataProperty()['temporalLabels']) }},
    temporalSeries: {{ json_encode($this->getChartDataProperty()['temporalSeries']) }}
})">
    @pushOnce('scripts')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    @endPushOnce

    <!-- MAIN DASHBOARD CONTAINER -->
    <div class="relative min-h-[600px] w-full group/main">

        <!-- 1. FLOATING LOADING MODAL (50% Width on Large Screens) -->
        <div wire:loading.delay.longest
            class="absolute inset-0 z-[100] flex items-center justify-center pointer-events-none p-4">
            <div class="w-full lg:w-1/2 max-w-xl bg-slate-950/80 backdrop-blur-2xl rounded-[3rem] p-10 shadow-[0_40px_120px_-20px_rgba(0,0,0,0.7)] border border-white/10 flex flex-col items-center gap-6 animate-in fade-in zoom-in duration-500 pointer-events-auto">
                <div class="relative">
                    <x-filament::loading-indicator class="w-14 h-14 text-claesen-orange" />
                    <div class="absolute inset-0 flex items-center justify-center">
                        <x-heroicon-m-bolt class="w-6 h-6 text-claesen-orange animate-pulse opacity-80" />
                    </div>
                </div>
                <div class="flex flex-col items-center gap-1 text-center">
                    <span class="text-[11px] font-black text-white uppercase tracking-[0.4em]">{{ __('employees/resource.dashboard.loading.focus') }}</span>
                    <span class="text-[9px] font-bold text-claesen-orange uppercase tracking-[0.2em] animate-pulse">{{ __('employees/resource.dashboard.loading.sync') }}</span>
                </div>
            </div>
        </div>


        <!-- 2. CONTENT WRAPPER -->
        <div wire:loading.class="opacity-20 blur-[6px] pointer-events-none transition-all duration-700" class="flex flex-col gap-12 duration-500">

            <!-- A. SIGNATURE FLOATING HUD -->
            <div class="glass-signature rounded-[2.5rem] p-8 relative overflow-hidden group">
                <!-- Background Mesh Hint -->
                <div class="absolute inset-0 bg-mesh-signature opacity-20 group-hover:opacity-30 transition-opacity pointer-events-none"></div>

                <div class="relative flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                    <div class="flex flex-col gap-1">
                        <div class="flex items-center gap-3">
                            <span class="text-[10px] font-bold text-claesen-orange uppercase tracking-[0.2em] mb-1">{{ __('employees/resource.dashboard.loading.stage') }}</span>
                            <div @class([ 'px-2 py-0.5 rounded-md text-[9px] font-bold uppercase tracking-wider' ,
                                $this->getActivePeriod() ? 'bg-claesen-orange/10 text-claesen-orange' : 'bg-indigo-500/10 text-indigo-500'
                                ])>
                                {{ $this->getActivePeriod() ? str_replace('_', ' ', $this->getActivePeriod()) : __('employees/resource.dashboard.custom_range') }}
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">
                            {{ __('employees/resource.sections.hours_distribution') }}
                        </h3>
                    </div>

                    <!-- Pre-defined Period Segmented Controls -->
                    <div class="flex items-center p-1 bg-slate-100/50 dark:bg-white/5 backdrop-blur-md rounded-xl border border-black/5 dark:border-white/10 overflow-x-auto max-w-full drop-shadow-sm">
                        @foreach(['last_month', 'this_month', 'last_quarter', 'last_semester', 'previous_year'] as $period)
                        <button wire:click="setPeriod('{{ $period }}')"
                            @class([ 'px-4 py-1.5 text-[11px] font-bold uppercase tracking-wider rounded-lg transition-all duration-200 whitespace-nowrap' ,
                            $this->getActivePeriod() == $period
                            ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm ring-1 ring-black/5 dark:ring-white/10'
                            : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-black/5 dark:hover:bg-white/5'
                            ])>
                            {{ __('employees/resource.placeholders.' . $period) }}
                        </button>
                        @endforeach
                    </div>

                    <!-- Date Context Trigger -->
                    <!-- Date Context Trigger -->
                    <button wire:click="mountAction('customRange')"
                        class="flex items-center gap-3 px-4 py-2 rounded-xl border border-black/5 dark:border-white/10 hover:bg-slate-50 dark:hover:bg-white/5 transition-colors text-left group">
                        <div class="flex flex-col">
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest group-hover:text-claesen-orange transition-colors">{{ __('employees/resource.dashboard.active_range') }}</span>
                            <span class="text-[12px] font-medium text-slate-900 dark:text-white tracking-tight">
                                {{ \Carbon\Carbon::parse($fromDate)->format('d M') }} — {{ \Carbon\Carbon::parse($toDate)->format('d M, Y') }}
                            </span>
                        </div>
                        <div class="text-slate-400 pl-2 border-l border-black/5 dark:border-white/10 transition-colors group-hover:text-claesen-orange">
                            <x-heroicon-m-calendar class="w-5 h-5" />
                        </div>
                    </button>
                </div>
            </div>

            <!-- B. CHARTS GRID -->
            <div x-show="totalHours > 0" x-transition.opacity.duration.500ms class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start" @style(['display: none' => $this->totalHours == 0])>

                <!-- Left Analysis: Allocation Pod -->
                <div class="lg:col-span-4 glass-signature rounded-[3rem] p-8 flex flex-col gap-4 inner-glow-orange">
                    <!-- Header -->
                    <div class="flex items-center justify-between">
                        <div class="flex flex-col">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mb-1">{{ __('employees/resource.dashboard.global_load') }}</span>
                            <span class="text-4xl font-bold text-slate-950 dark:text-white tracking-signature">{{ round($totalHours) }}h</span>
                        </div>
                        <div @class(['w-12 h-12 flex items-center justify-center rounded-2xl glass-signature text-claesen-orange'])>
                            <x-heroicon-m-bolt class="w-6 h-6" />
                        </div>
                    </div>

                    <!-- APEX DONUT CHART -->
                    <div class="relative w-full min-h-[220px]" x-ref="donutChartContainer"></div>

                    <div class="w-full h-px border-t border-black/5 dark:border-white/5 my-2"></div>

                    <!-- APEX AREA CHART -->
                    <div class="w-full flex-1">
                        <span x-text="temporalTitle" class="text-[9px] font-bold text-slate-400 uppercase tracking-widest pl-2">Daily Trend</span>
                        <div class="relative w-full h-[140px] mt-2" x-ref="areaChartContainer"></div>
                    </div>
                </div>

                <!-- Right Analysis: Project Showcase Feed -->
                <div class="lg:col-span-8 space-y-10">
                    <div class="flex items-center justify-between px-4 pb-2 border-b border-black/5 dark:border-white/5">
                        <div class="flex items-center gap-4">
                            <div class="w-2 h-8 bg-claesen-orange rounded-full"></div>
                            <h4 class="text-lg font-black text-slate-950 dark:text-white uppercase tracking-[0.25em]">{{ __('employees/resource.dashboard.project_showcase') }}</h4>
                            <span class="px-3 py-1 bg-claesen-orange/10 border border-claesen-orange/20 rounded-xl text-[10px] font-black text-claesen-orange">{{ $timeline->total() }}</span>
                        </div>
                        <button class="text-[10px] font-bold text-slate-400 hover:text-claesen-orange transition-colors uppercase tracking-widest">{{ __('employees/resource.dashboard.view_archives') }}</button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        @forelse($timeline as $item)
                        <a href="{{ route('filament.admin.resources.project-insights.view', ['record' => $item['project_id'] ?? 0]) }}"
                            class="glass-signature rounded-[2.5rem] p-8 group transition-all duration-500 hover:-translate-y-2 hover:inner-glow-orange flex flex-col justify-between h-[230px]">

                            <div class="flex items-start justify-between">
                                <div class="flex flex-col gap-1 max-w-[70%]">
                                    <span class="text-[9px] font-bold text-claesen-orange uppercase tracking-[0.15em]">
                                        {{ __('employees/resource.dashboard.project_insight') }}
                                    </span>
                                    <h4 class="text-lg font-bold text-slate-950 dark:text-white tracking-signature group-hover:text-claesen-orange transition-colors truncate">
                                        {{ $item['project_name'] }}
                                    </h4>
                                    <span class="text-[9px] font-mono text-slate-400 tracking-tighter opacity-70 group-hover:opacity-100 uppercase">
                                        #{{ $item['project_code'] }}
                                    </span>
                                </div>
                                <div class="flex flex-col items-end">
                                    <span class="text-2xl font-bold text-slate-950 dark:text-white tracking-signature">{{ floor($item['total_hours']) }}h</span>
                                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest leading-none">{{ $item['month_label'] }}</span>
                                </div>
                            </div>

                            <div class="flex flex-col gap-3">
                                <div class="flex items-center justify-between text-[10px] font-black text-slate-950 dark:text-white uppercase tracking-widest">
                                    <span>{{ __('employees/resource.dashboard.efficiency') }}</span>
                                    <span>{{ $item['percentage'] }}%</span>
                                </div>
                                <div class="h-1.5 w-full glass-signature bg-slate-50 dark:bg-white/5 rounded-full overflow-hidden">
                                    <div class="h-full bg-indigo-500 rounded-full transition-all duration-1000 group-hover:bg-gradient-to-r group-hover:from-indigo-500 group-hover:to-rose-500" @style(['width: ' . $item['percentage'] . '%'])></div>
                                </div>
                                <div class="flex items-center gap-2 mt-1">
                                    <div class="flex items-center justify-center w-5 h-5 rounded-full bg-emerald-500/10 border border-emerald-500/20 shrink-0">
                                        <x-heroicon-m-check-circle class="w-3.5 h-3.5 text-emerald-500" />
                                    </div>
                                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest whitespace-nowrap">{{ __('employees/resource.dashboard.verified_match') }}</span>
                                </div>
                            </div>
                        </a>
                        @empty
                        <div class="col-span-full py-20 glass-signature rounded-[3rem] border-dashed flex flex-col items-center justify-center text-center">
                            <x-heroicon-o-puzzle-piece class="w-12 h-12 text-slate-200 dark:text-slate-800 mb-4" />
                            <p class="text-xs font-bold uppercase text-slate-400 tracking-[0.2em]">Synchronization in progress...</p>
                        </div>
                        @endforelse
                    </div>

                    <div class="px-4">
                        {{ $timeline->links('livewire::simple-tailwind', data: ['scrollTo' => false]) }}
                    </div>
                </div>
            </div>

            <!-- C. EMPTY STATE -->
            <div x-cloak x-show="totalHours == 0" x-transition.opacity.duration.500ms class="w-full glass-signature inner-glow-orange rounded-[3rem] p-16 flex flex-col items-center justify-center text-center opacity-80 min-h-[400px]" @style(['display: none' => $this->totalHours > 0])>
                <div class="w-24 h-24 mb-6 rounded-[2rem] glass-signature flex items-center justify-center text-slate-300 dark:text-slate-600 shadow-[inset_0_4px_10px_rgba(255,255,255,0.2)]">
                    <x-heroicon-o-folder-open class="w-12 h-12 text-claesen-orange/50" />
                </div>
                <h3 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight mb-3">
                    {{ __('employees/resource.empty_state.title') }}
                </h3>
                <p class="text-slate-500 dark:text-slate-400 font-medium max-w-sm mb-10 leading-relaxed text-sm">
                    {!! str_replace(':name', '<strong class="text-claesen-orange">' . e($record->name) . '</strong>', __('employees/resource.empty_state.description')) !!}
                </p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('employeeDashboard', (initialData) => ({
                totalHours: initialData.totalHours,
                donutChart: null,
                areaChart: null,
                distributionLabels: initialData.distributionLabels,
                distributionSeries: initialData.distributionSeries,
                temporalTitle: initialData.temporalTitle,
                temporalLabels: initialData.temporalLabels,
                temporalSeries: initialData.temporalSeries,

                init() {
                    if (this.totalHours > 0) {
                        this.initDonutChart();
                        this.initAreaChart();
                    }

                    window.addEventListener('statsUpdated', (event) => {
                        this.totalHours = event.detail[0].totalHours;
                        this.temporalTitle = event.detail[0].temporalTitle;

                        if (this.totalHours > 0) {
                            this.$nextTick(() => {
                                // Initialize if they were previously hidden
                                if (!this.donutChart) this.initDonutChart();
                                if (!this.areaChart) this.initAreaChart();

                                this.updateCharts(
                                    event.detail[0].distributionLabels,
                                    event.detail[0].distributionSeries,
                                    event.detail[0].temporalLabels,
                                    event.detail[0].temporalSeries
                                );
                            });
                        }
                    });
                },

                initDonutChart() {
                    const colors = ['#6366f1', '#f97316', '#10b981', '#f43f5e'];

                    const options = {
                        series: this.distributionSeries.map(Number),
                        labels: this.distributionLabels,
                        chart: {
                            type: 'donut',
                            height: 240,
                            background: 'transparent',
                            fontFamily: 'Outfit, sans-serif'
                        },
                        plotOptions: {
                            pie: {
                                donut: {
                                    size: '75%',
                                    labels: {
                                        show: true,
                                        name: {
                                            show: true,
                                            fontSize: '10px',
                                            fontWeight: '700',
                                            color: '#94a3b8'
                                        },
                                        value: {
                                            show: true,
                                            fontSize: '24px',
                                            fontWeight: '900',
                                            color: '#1e293b',
                                            formatter: (val) => Number(val).toFixed(1) + 'h'
                                        },
                                        total: {
                                            show: true,
                                            showAlways: true,
                                            label: 'Total',
                                            fontSize: '10px',
                                            fontWeight: '700',
                                            color: '#94a3b8',
                                            formatter: function(w) {
                                                const total = w.globals.seriesTotals.reduce((a, b) => {
                                                    return a + b
                                                }, 0);
                                                return total.toFixed(0) + 'h';
                                            }
                                        }
                                    }
                                }
                            }
                        },
                        colors: colors,
                        dataLabels: {
                            enabled: false
                        },
                        stroke: {
                            width: 0,
                            colors: ['transparent']
                        },
                        legend: {
                            position: 'bottom',
                            fontSize: '10px',
                            fontWeight: '600',
                            labels: {
                                colors: '#64748b'
                            },
                            markers: {
                                width: 8,
                                height: 8
                            }
                        },
                        tooltip: {
                            theme: 'dark',
                            fillSeriesColor: false,
                            y: {
                                formatter: (val) => Number(val).toFixed(1) + ' hours'
                            }
                        }
                    };

                    this.donutChart = new ApexCharts(this.$refs.donutChartContainer, options);
                    this.donutChart.render();
                },

                initAreaChart() {
                    const options = {
                        series: this.temporalSeries,
                        chart: {
                            type: 'bar',
                            stacked: true,
                            height: 140,
                            toolbar: {
                                show: false
                            },
                            background: 'transparent',
                            fontFamily: 'Inter, sans-serif'
                        },
                        colors: ['#6366f1', '#f97316', '#10b981'], // Matching: Werf, Laden, Mobiliteit
                        plotOptions: {
                            bar: {
                                borderRadius: 2,
                                columnWidth: '50%',
                                dataLabels: {
                                    total: {
                                        enabled: false
                                    }
                                }
                            }
                        },
                        dataLabels: {
                            enabled: false
                        },
                        stroke: {
                            width: 0,
                            colors: ['transparent']
                        },
                        xaxis: {
                            categories: this.temporalLabels,
                            labels: {
                                show: true,
                                style: {
                                    colors: '#94a3b8',
                                    fontSize: '9px',
                                    fontWeight: 600,
                                    fontFamily: 'Inter, sans-serif'
                                }
                            },
                            tickAmount: this.temporalLabels.length > 15 ? 6 : undefined,
                            axisBorder: {
                                show: false
                            },
                            axisTicks: {
                                show: false
                            },
                            tooltip: {
                                enabled: false
                            }
                        },
                        yaxis: {
                            labels: {
                                show: false
                            }
                        },
                        grid: {
                            show: false,
                            padding: {
                                top: 0,
                                right: 0,
                                bottom: 0,
                                left: 0
                            }
                        },
                        legend: {
                            show: false
                        },
                        tooltip: {
                            theme: 'dark',
                            shared: true,
                            intersect: false,
                            y: {
                                formatter: (val) => Number(val).toFixed(1) + 'h'
                            }
                        }
                    };

                    this.areaChart = new ApexCharts(this.$refs.areaChartContainer, options);
                    this.areaChart.render();
                },

                updateCharts(dLabels, dSeries, temporalLbs, temporalSer) {
                    if (this.donutChart) {
                        this.donutChart.updateOptions({
                            labels: dLabels
                        });
                        this.donutChart.updateSeries(dSeries.map(Number));
                    }
                    if (this.areaChart) {
                        this.areaChart.updateOptions({
                            xaxis: {
                                categories: temporalLbs,
                                tickAmount: temporalLbs.length > 15 ? 6 : undefined
                            }
                        });
                        this.areaChart.updateSeries(temporalSer);
                    }
                }
            }));
        });
    </script>

    <x-filament-actions::modals />
</div>
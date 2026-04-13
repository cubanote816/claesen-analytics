@php
    $state = (float) $getState();
    // Baseline: if a project has 20h or more, it's a "Major focus" (full bar)
    $percentage = min(($state / 20) * 100, 100);
@endphp

<div class="mt-1 mb-2 px-1">
    <div class="relative h-1 w-full bg-slate-200/50 dark:bg-black/40 rounded-full overflow-hidden shadow-inner flex p-0">
        <div class="absolute inset-y-0 left-0 bg-gradient-to-r from-primary-500 to-indigo-600 rounded-full transition-all duration-1000 shadow-[0_0_8px_rgba(99,102,241,0.3)]"
             style="width: {{ $percentage }}%"></div>
    </div>
</div>

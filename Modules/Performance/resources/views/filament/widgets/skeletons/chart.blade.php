<div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm">
    <div class="flex justify-between items-center mb-6">
        <x-performance::skeleton height="h-6" width="w-48" />
        <x-performance::skeleton height="h-4" width="w-24" />
    </div>
    
    <div class="relative h-[300px] w-full flex items-end gap-2 px-2">
        @for ($i = 0; $i < 12; $i++)
            <x-performance::skeleton height="{{ rand(40, 90) }}%" width="flex-1" />
        @endfor
    </div>
</div>

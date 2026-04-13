<div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
    @for ($i = 0; $i < 3; $i++)
        <div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm space-y-4">
            <x-performance::skeleton height="h-4" width="w-24" />
            <x-performance::skeleton height="h-8" width="w-16" />
            <div class="flex gap-2">
                <x-performance::skeleton height="h-3" width="w-32" />
            </div>
        </div>
    @endfor
</div>

<div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm space-y-8">
    <div class="flex justify-between items-center">
        <div class="flex items-center gap-3">
            <x-performance::skeleton height="h-10" width="w-10" rounded="rounded-lg" />
            <x-performance::skeleton height="h-6" width="w-64" />
        </div>
        <x-performance::skeleton height="h-6" width="w-32" rounded="rounded-full" />
    </div>

    <div class="space-y-4">
        <x-performance::skeleton height="h-20" width="w-full" />
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @for ($i = 0; $i < 3; $i++)
                <div class="p-5 border border-gray-100 dark:border-gray-800 rounded-2xl space-y-4">
                    <div class="flex justify-between">
                        <x-performance::skeleton height="h-3" width="w-16" />
                        <x-performance::skeleton height="h-4" width="w-20" />
                    </div>
                    <x-performance::skeleton height="h-5" width="w-32" />
                    <x-performance::skeleton height="h-10" width="w-24" />
                    <hr class="border-gray-100 dark:border-gray-900">
                    <x-performance::skeleton height="h-4" width="w-full" />
                </div>
            @endfor
        </div>
    </div>
</div>

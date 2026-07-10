@php
    /** @var \Illuminate\Support\Collection<int, \Modules\FieldOps\Models\Complex> $complexes */
    $complexes = $getState();
@endphp

<div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
    <table class="w-full text-start">
        <thead>
            <tr class="divide-x divide-gray-200 bg-gray-50 dark:divide-white/10 dark:bg-white/5">
                <th class="px-4 py-2 text-start text-xs font-medium text-gray-500 dark:text-gray-400">
                    {{ __('fieldops::resource.complexes.fields.name') }}
                </th>
                <th class="px-4 py-2 text-start text-xs font-medium text-gray-500 dark:text-gray-400">
                    {{ __('fieldops::resource.complexes.fields.city') }}
                </th>
                <th class="px-4 py-2 text-end text-xs font-medium text-gray-500 dark:text-gray-400">
                    {{ __('fieldops::resource.complexes.fields.terrains_count') }}
                </th>
                <th class="w-6"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-white/10 [&>tr:last-child>td]:pb-4">
            @forelse ($complexes as $complex)
                @php $complexUrl = \Modules\FieldOps\Filament\Resources\ComplexResource::getUrl('view', ['record' => $complex]); @endphp
                <tr
                    class="cursor-pointer divide-x divide-gray-200 transition-colors hover:bg-gray-50 dark:divide-white/10 dark:hover:bg-gray-950/50"
                    onclick="window.location='{{ $complexUrl }}'"
                >
                    <td class="px-4 py-3 text-sm">
                        <a
                            href="{{ $complexUrl }}"
                            class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                        >
                            {{ $complex->name }}
                        </a>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        {{ $complex->city ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-end font-mono text-sm text-gray-500 dark:text-gray-400">
                        {{ $complex->terrains_count ?? $complex->terrains()->count() }}
                    </td>
                    <td class="px-2 py-3 text-end text-gray-400 dark:text-gray-500">&rsaquo;</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ __('fieldops::resource.clients.no_complexes') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

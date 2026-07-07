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
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
            @forelse ($complexes as $complex)
                <tr class="divide-x divide-gray-200 dark:divide-white/10">
                    <td class="px-4 py-3 text-sm">
                        <a
                            href="{{ \Modules\FieldOps\Filament\Resources\ComplexResource::getUrl('edit', ['record' => $complex]) }}"
                            class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                        >
                            {{ $complex->name }}
                        </a>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        {{ $complex->city ?? '—' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ __('fieldops::resource.clients.no_complexes') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

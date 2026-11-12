{{-- Audience preview modal: $total, $prospects (max 100, ordered by name), $remaining, $error --}}
<div class="space-y-3 text-sm">
    @if ($error !== null)
        <div class="rounded-lg border border-danger-300 bg-danger-50 px-4 py-3 text-danger-700 dark:border-danger-700 dark:bg-gray-800 dark:text-danger-400">
            <p class="font-medium">{{ __('mailing::resource.notifications.segment_error') }}</p>
            <p>{{ $error }}</p>
        </div>
    @else
        <p class="font-medium text-gray-900 dark:text-white">
            {{ __('mailing::resource.preview.total', ['count' => $total]) }}
        </p>

        @if ($total === 0)
            <p class="text-gray-500 dark:text-gray-400">{{ __('mailing::resource.preview.empty') }}</p>
        @else
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <th class="px-4 py-2 text-left">{{ __('mailing::resource.preview.club') }}</th>
                            <th class="px-4 py-2 text-left">{{ __('mailing::resource.preview.sport') }}</th>
                            <th class="px-4 py-2 text-left">{{ __('mailing::resource.preview.federation') }}</th>
                            <th class="px-4 py-2 text-left">{{ __('mailing::resource.preview.region') }}</th>
                            <th class="px-4 py-2 text-left">{{ __('mailing::resource.preview.language') }}</th>
                            <th class="px-4 py-2 text-left">{{ __('mailing::resource.preview.contact_person') }}</th>
                            <th class="px-4 py-2 text-left">{{ __('mailing::resource.preview.emails') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($prospects as $prospect)
                            @php
                                $emails = $prospect->locations
                                    ->flatMap(fn ($location) => explode(',', (string) $location->email))
                                    ->map(fn ($email) => trim($email))
                                    ->filter()
                                    ->unique()
                                    ->implode(', ');
                            @endphp
                            <tr class="bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ filled($prospect->name) ? $prospect->name : '—' }}</td>
                                <td class="px-4 py-2">{{ filled($prospect->type) ? $prospect->type : '—' }}</td>
                                <td class="px-4 py-2">{{ filled($prospect->federation) ? $prospect->federation : '—' }}</td>
                                <td class="px-4 py-2">{{ filled($prospect->region?->name) ? $prospect->region->name : '—' }}</td>
                                <td class="px-4 py-2">{{ filled($prospect->language) ? $prospect->language : '—' }}</td>
                                <td class="px-4 py-2">{{ filled($prospect->contact_person) ? $prospect->contact_person : '—' }}</td>
                                <td class="px-4 py-2">{{ $emails !== '' ? $emails : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($remaining > 0)
                <p class="text-gray-500 dark:text-gray-400">{{ __('mailing::resource.preview.more', ['count' => $remaining]) }}</p>
            @endif
        @endif

        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('mailing::resource.preview.note') }}</p>
    @endif
</div>

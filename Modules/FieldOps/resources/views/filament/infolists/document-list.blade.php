@php
    /** @var \Illuminate\Support\Collection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $documents */
    $documents = $getState();
@endphp

@if ($documents->isNotEmpty())
    <ul class="flex flex-col gap-2">
        @foreach ($documents as $item)
            <li>
                <a
                    href="{{ route('fieldops.admin.media.show', $item) }}"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                >
                    <x-heroicon-o-document-text class="h-5 w-5 shrink-0" />
                    <span>{{ $item->file_name }}</span>
                    <span class="text-gray-400 dark:text-gray-500">({{ number_format($item->size / 1024, 0) }} KB)</span>
                </a>
            </li>
        @endforeach
    </ul>
@else
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('fieldops::resource.media.no_documents') }}</p>
@endif

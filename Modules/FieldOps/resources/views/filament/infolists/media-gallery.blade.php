@php
    /** @var \Illuminate\Support\Collection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media */
    $media = $getState();
@endphp

@if ($media->isNotEmpty())
    <div class="flex flex-wrap gap-3">
        @foreach ($media as $item)
            <a href="{{ $item->getUrl() }}" target="_blank" rel="noopener" class="block h-20 w-20 overflow-hidden rounded-lg border border-gray-200 dark:border-white/10">
                <img src="{{ $item->getUrl('thumb') ?: $item->getUrl() }}" alt="{{ $item->name }}" class="h-full w-full object-cover" />
            </a>
        @endforeach
    </div>
@else
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('fieldops::resource.media.no_photos') }}</p>
@endif

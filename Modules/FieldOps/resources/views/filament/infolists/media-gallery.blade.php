@php
    /** @var \Illuminate\Support\Collection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media */
    $media = $getState();
@endphp

@if ($media->isNotEmpty())
    <div class="flex flex-wrap gap-3">
        @foreach ($media as $item)
            @php
                // The 'local' disk backing FieldOps media is intentionally private
                // (storage/app/private, no public URL mapping) — $item->getUrl() silently
                // guesses the public-disk convention and 404s. Route through the
                // session-authenticated admin media route instead.
                $fullUrl = route('fieldops.admin.media.show', $item);
                $thumbUrl = $item->hasGeneratedConversion('thumb')
                    ? route('fieldops.admin.media.show', ['media' => $item, 'conversion' => 'thumb'])
                    : $fullUrl;
            @endphp
            <a href="{{ $fullUrl }}" target="_blank" rel="noopener" class="block h-20 w-20 overflow-hidden rounded-lg border border-gray-200 dark:border-white/10">
                <img src="{{ $thumbUrl }}" alt="{{ $item->name }}" class="h-full w-full object-cover" />
            </a>
        @endforeach
    </div>
@else
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('fieldops::resource.media.no_photos') }}</p>
@endif

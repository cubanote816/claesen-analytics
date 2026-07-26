@php
    /** @var \Illuminate\Support\Collection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media */
    $media = $getState();
@endphp

@if ($media->isNotEmpty())
    <div class="flex flex-wrap gap-3">
        @foreach ($media as $item)
            <video controls preload="metadata" class="h-40 w-56 rounded-lg border border-gray-200 object-cover dark:border-white/10">
                {{-- See media-gallery.blade.php for why this doesn't use $item->getUrl(). --}}
                <source src="{{ route('fieldops.admin.media.show', $item) }}" type="{{ $item->mime_type }}">
            </video>
        @endforeach
    </div>
@else
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('fieldops::resource.media.no_videos') }}</p>
@endif

@props([
    'height' => 'h-32',
    'width' => 'w-full',
    'rounded' => 'rounded-xl',
])

<div {{ $attributes->merge(['class' => "$height $width $rounded bg-gray-200 dark:bg-gray-800 animate-pulse relative overflow-hidden"]) }}>
    <!-- Shimmer effect -->
    <div class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/20 dark:via-white/5 to-transparent animate-[shimmer_2s_infinite]"></div>
</div>

<style>
    @keyframes shimmer {
        100% {
            transform: translateX(100%);
        }
    }
</style>

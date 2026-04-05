{{-- Divider --}}
<div class="relative flex items-center justify-center my-6">
    <div class="absolute inset-0 flex items-center">
        <div class="w-full border-t border-gray-200 dark:border-white/10"></div>
    </div>
    <div class="relative px-3 text-sm bg-white dark:bg-gray-900 text-gray-500 font-medium uppercase tracking-wider">
        Of
    </div>
</div>

{{-- Microsoft Login Button --}}
<div class="mt-4">
    <a href="{{ route('auth.microsoft.redirect') }}" 
       class="flex w-full items-center justify-center gap-3 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:ring-transparent dark:bg-white/5 dark:text-white dark:ring-white/20 dark:hover:bg-white/10 transition-all duration-200">
        <svg class="h-5 w-5" viewBox="0 0 23 23" xmlns="http://www.w3.org/2000/svg">
            <path fill="#f3f3f3" d="M0 0h23v23H0z"/>
            <path fill="#f25022" d="M1 1h10v10H1z"/>
            <path fill="#7fba00" d="M12 1h10v10H12z"/>
            <path fill="#00a4ef" d="M1 12h10v10H1z"/>
            <path fill="#ffb900" d="M12 12h10v10H12z"/>
        </svg>
        <span class="text-sm font-medium">Aanmelden met Microsoft</span>
    </a>
</div>

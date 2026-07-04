{{--
    CLA-208: replaces Livewire's built-in native confirm() on HTTP 419 (session/CSRF
    expired) with a branded modal. Livewire.hook('request', ...) exposes the same
    preventDefault() that guards its own confirm() call (vendor/livewire/livewire/dist/livewire.js),
    so calling it here suppresses the native dialog instead of racing it.
--}}
<div
    x-data="{ show: false }"
    x-on:open-session-expired-modal.window="show = true"
    x-show="show"
    x-cloak
    style="display: none;"
    class="fixed inset-0 z-[1000] flex items-center justify-center p-4"
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="session-expired-title"
>
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>

    <div class="relative w-full max-w-sm rounded-2xl border border-white/10 bg-gray-950 p-8 text-center shadow-2xl">
        <img
            src="{{ asset('img/brand-logo-dark.png') }}"
            alt="Claesen Verlichting"
            class="mx-auto mb-6 h-8 opacity-90"
        >

        <div class="mx-auto mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-[#00aeef]/10">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#00aeef" stroke-width="1.5" class="h-6 w-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>
        </div>

        @php
            $locale = request()->getPreferredLanguage(['nl', 'en']) === 'nl' ? 'nl' : 'en';
        @endphp

        <h2 id="session-expired-title" class="mb-2 text-lg font-semibold text-white">
            {{ $locale === 'nl' ? 'Je sessie is verlopen' : 'Your session has expired' }}
        </h2>

        <p class="mb-6 text-sm text-gray-400">
            {{ $locale === 'nl'
                ? 'Om veiligheidsredenen ben je automatisch uitgelogd. Vernieuw de pagina om verder te gaan.'
                : 'For security reasons you were logged out automatically. Reload the page to continue.' }}
        </p>

        <button
            type="button"
            x-on:click="window.location.reload()"
            class="w-full rounded-lg bg-[#00aeef] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#0098d1]"
        >
            {{ $locale === 'nl' ? 'Pagina vernieuwen' : 'Reload page' }}
        </button>
    </div>
</div>

<script>
    (function () {
        function registerSessionExpiredHook() {
            if (window.__sessionExpiredHookRegistered) return;
            window.__sessionExpiredHookRegistered = true;

            Livewire.hook('request', ({ fail }) => {
                fail(({ status, preventDefault }) => {
                    if (status === 419) {
                        preventDefault();
                        window.dispatchEvent(new CustomEvent('open-session-expired-modal'));
                    }
                });
            });
        }

        if (window.Livewire) {
            registerSessionExpiredHook();
        } else {
            window.addEventListener('livewire:initialized', registerSessionExpiredHook);
        }
    }());
</script>

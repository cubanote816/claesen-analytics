<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('mailing::preferences.page_title') }} | Claesen Verlichting</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #0f172a; color: #f8fafc; }
        .glass-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .accent-gradient { background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%); }
        .toggle-checkbox:checked { right: 0; border-color: #d97706; }
        .toggle-checkbox:checked + .toggle-label { background-color: #d97706; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="max-w-lg w-full glass-card rounded-3xl p-8 space-y-6">

        {{-- Logo --}}
        <div class="flex justify-center mb-6">
            <img src="https://claesen-verlichting.be/v1/assets/brand-logo-dark.png"
                 alt="Claesen Outdoor Lighting" class="h-12 w-auto">
        </div>

        {{-- Header --}}
        <div class="text-center space-y-2">
            <div class="flex justify-center mb-4">
                <div class="w-14 h-14 accent-gradient rounded-2xl flex items-center justify-center shadow-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94
                                 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724
                                 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572
                                 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31
                                 -.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724
                                 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
            </div>
            <h1 class="text-2xl font-semibold text-white">
                {{ __('mailing::preferences.page_title') }}
            </h1>
            <p class="text-slate-400">
                {{ __('mailing::preferences.page_subtitle', ['name' => $prospect->name]) }}
            </p>
        </div>

        {{-- Success banner --}}
        @if(session('saved'))
            <div class="bg-emerald-900/50 border border-emerald-500/30 rounded-2xl px-4 py-3 text-emerald-300 text-sm text-center">
                {{ __('mailing::preferences.saved_success') }}
            </div>
        @endif

        {{-- Global unsubscribe notice --}}
        @if($prospect->unsubscribed_at)
            <div class="bg-amber-900/40 border border-amber-500/30 rounded-2xl px-4 py-3 text-amber-300 text-sm text-center">
                {{ __('mailing::preferences.global_unsubscribe_notice') }}
            </div>
        @endif

        {{-- Preferences form --}}
        <form action="{{ route('mailing.preferences.update', ['prospect' => $prospect->id, 'token' => $token]) }}"
              method="POST" class="space-y-4">
            @csrf

            @foreach($categories as $key => $category)
                @php
                    $isSubscribed = $preferences[$key] ?? true;
                    $label        = $category['label'][$locale] ?? $category['label']['en'] ?? $key;
                    $description  = $category['description'][$locale] ?? $category['description']['en'] ?? '';
                @endphp

                <div class="flex items-start gap-4 bg-slate-800/50 rounded-2xl px-5 py-4 border
                            {{ $isSubscribed ? 'border-amber-500/30' : 'border-slate-700/50' }}
                            transition-colors">
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-white text-sm">{{ $label }}</p>
                        @if($description)
                            <p class="text-slate-400 text-xs mt-0.5">{{ $description }}</p>
                        @endif
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 mt-0.5">
                        <input type="checkbox"
                               name="{{ $key }}"
                               value="1"
                               class="sr-only peer"
                               {{ $isSubscribed ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-slate-600 peer-focus:outline-none rounded-full peer
                                    peer-checked:after:translate-x-full peer-checked:after:border-white
                                    after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                    after:bg-white after:border-gray-300 after:border after:rounded-full
                                    after:h-5 after:w-5 after:transition-all
                                    peer-checked:bg-amber-500"></div>
                    </label>
                </div>
            @endforeach

            <div class="pt-2">
                <button type="submit"
                        class="w-full py-4 px-6 accent-gradient text-black font-semibold rounded-2xl
                               hover:opacity-90 transition-all shadow-xl active:scale-95 text-sm">
                    {{ __('mailing::preferences.save_button') }}
                </button>
            </div>
        </form>

        {{-- Footer link --}}
        <div class="text-center pt-2">
            <a href="https://claesen-verlichting.be"
               class="text-amber-500 hover:text-amber-400 transition-colors font-medium text-sm">
                &larr; claesen-verlichting.be
            </a>
        </div>
    </div>
</body>
</html>

@php
    $preferredLocale = request()->getPreferredLanguage(['nl', 'en']);
    $locale = ($preferredLocale === 'nl') ? 'nl' : 'en';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="30">
    <title>{{ $locale === 'nl' ? 'Even geduld — Onderhoud bezig' : 'One moment — Maintenance in progress' }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,600,700" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        :root {
            --brand-yellow: #F8B803;
            --brand-dark: #0a0a0a;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--brand-dark);
            color: #ffffff;
            overflow: hidden;
        }

        .glow {
            text-shadow: 0 0 10px rgba(248, 184, 3, 0.5), 0 0 20px rgba(248, 184, 3, 0.3);
        }

        @keyframes pulse-glow {
            0%, 100% { opacity: 0.6; text-shadow: 0 0 8px rgba(248, 184, 3, 0.3); }
            50%       { opacity: 1;   text-shadow: 0 0 18px rgba(248, 184, 3, 0.7), 0 0 35px rgba(248, 184, 3, 0.4); }
        }

        .pulse { animation: pulse-glow 2.5s ease-in-out infinite; }

        @keyframes spin-slow {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }

        .spin-slow { animation: spin-slow 8s linear infinite; }

        @keyframes bounce-dot {
            0%, 80%, 100% { transform: translateY(0);    opacity: 0.4; }
            40%            { transform: translateY(-8px); opacity: 1;   }
        }

        .dot { animation: bounce-dot 1.4s ease-in-out infinite; }
        .dot:nth-child(2) { animation-delay: 0.2s; }
        .dot:nth-child(3) { animation-delay: 0.4s; }

        .wire-container {
            position: relative;
            height: 210px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }

        .wire {
            width: 2px;
            height: 100px;
            background: linear-gradient(to bottom, #555, #333);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .bulb-holder {
            width: 42px;
            height: 18px;
            background: #555;
            border-radius: 4px;
            margin-top: -1px;
        }

        .bulb {
            width: 64px;
            height: 84px;
            background: rgba(248, 184, 3, 0.08);
            border: 2px solid rgba(248, 184, 3, 0.4);
            border-radius: 50% 50% 45% 45%;
            margin-top: -4px;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: inset 0 0 25px rgba(248, 184, 3, 0.12), 0 0 20px rgba(248, 184, 3, 0.15);
            position: relative;
        }

        .filament {
            width: 28px;
            height: 28px;
            border: 2.5px solid var(--brand-yellow);
            border-radius: 50%;
            border-bottom-color: transparent;
            box-shadow: 0 -4px 12px rgba(248, 184, 3, 0.6);
        }

        /* Gear overlay — installation in progress */
        .gear-overlay {
            position: absolute;
            top: -14px;
            right: -18px;
            width: 28px;
            height: 28px;
            opacity: 0.9;
        }

        .progress-bar-track {
            background: rgba(255,255,255,0.08);
            border-radius: 999px;
            overflow: hidden;
        }

        @keyframes progress-indeterminate {
            0%   { transform: translateX(-100%); width: 60%; }
            50%  { transform: translateX(80%);   width: 60%; }
            100% { transform: translateX(200%);  width: 60%; }
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, transparent, var(--brand-yellow), transparent);
            border-radius: 999px;
            animation: progress-indeterminate 2s ease-in-out infinite;
        }
    </style>
</head>
<body class="flex flex-col items-center justify-center min-h-screen p-6">

    {{-- Logo --}}
    <div class="absolute top-8 left-8">
        <img src="{{ asset('img/brand-logo-dark.png') }}" alt="Claesen Verlichting" class="h-10 opacity-80">
    </div>

    {{-- Refresh countdown hint --}}
    <div class="absolute top-8 right-8 text-xs text-gray-600 uppercase tracking-widest">
        {{ $locale === 'nl' ? 'Herproberen over 30s' : 'Retrying in 30s' }}
    </div>

    {{-- Animated bulb --}}
    <div class="wire-container mb-6">
        <div class="wire flex flex-col items-center">
            <div class="bulb-holder"></div>
            <div class="bulb">
                <div class="filament spin-slow"></div>

                {{-- Gear icon --}}
                <div class="gear-overlay">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#F8B803" stroke-width="1.5" class="spin-slow" style="animation-direction: reverse; animation-duration: 5s;">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Content --}}
    <div class="text-center max-w-md">
        <h1 class="text-4xl font-bold mb-3 pulse glow">
            @if($locale === 'nl')
                Even geduld&hellip;
            @else
                One moment&hellip;
            @endif
        </h1>

        <p class="text-lg text-gray-300 mb-2">
            @if($locale === 'nl')
                Het platform wordt bijgewerkt.
            @else
                The platform is being updated.
            @endif
        </p>

        <p class="text-sm text-gray-500 mb-8">
            @if($locale === 'nl')
                We zijn zo terug. De pagina probeert het automatisch opnieuw.
            @else
                We'll be right back. This page retries automatically.
            @endif
        </p>

        {{-- Dots --}}
        <div class="flex justify-center gap-2 mb-8">
            <span class="dot inline-block w-2 h-2 rounded-full bg-yellow-400"></span>
            <span class="dot inline-block w-2 h-2 rounded-full bg-yellow-400"></span>
            <span class="dot inline-block w-2 h-2 rounded-full bg-yellow-400"></span>
        </div>

        {{-- Progress bar --}}
        <div class="progress-bar-track w-48 h-1 mx-auto">
            <div class="progress-bar-fill"></div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="absolute bottom-6 text-xs text-gray-700">
        &copy; {{ date('Y') }} Claesen Verlichting BV
    </div>

    {{-- Background decoration --}}
    <div class="absolute bottom-0 right-0 p-12 opacity-5 pointer-events-none select-none">
        <svg width="380" height="380" viewBox="0 0 100 100" fill="none" stroke="currentColor">
            <path d="M10,50 L90,50 M50,10 L50,90 M30,30 L70,70 M70,30 L30,70" stroke-width="0.5"/>
            <circle cx="50" cy="50" r="40" stroke-width="0.5"/>
            <circle cx="50" cy="50" r="20" stroke-width="0.5"/>
        </svg>
    </div>

</body>
</html>

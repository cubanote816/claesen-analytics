@php
    $preferredLocale = request()->getPreferredLanguage(['nl', 'en']);
    $locale = ($preferredLocale === 'nl') ? 'nl' : 'en';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $locale === 'nl' ? '404 - Kortsluiting!' : '404 - Short Circuit!' }}</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,600,700" rel="stylesheet" />

    <!-- Tailwind -->
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

        @keyframes flicker {
            0%, 19.999%, 22%, 62.999%, 64%, 64.999%, 70%, 100% {
                opacity: 0.99;
                text-shadow: 0 0 10px rgba(248, 184, 3, 0.5), 0 0 20px rgba(248, 184, 3, 0.3);
            }
            20%, 21.999%, 63%, 63.999%, 65%, 69.999% {
                opacity: 0.4;
                text-shadow: none;
            }
        }

        .flicker {
            animation: flicker 3s linear infinite;
        }

        @keyframes spark {
            0%, 100% { transform: scale(1); opacity: 0.2; }
            50% { transform: scale(1.2); opacity: 0.8; filter: brightness(1.5); }
        }

        .spark {
            position: absolute;
            width: 4px;
            height: 4px;
            background: var(--brand-yellow);
            border-radius: 50%;
            filter: blur(1px);
        }

        .wire-container {
            position: relative;
            height: 200px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }

        .wire {
            width: 2px;
            height: 100px;
            background: #333;
            position: relative;
        }

        .bulb-holder {
            width: 40px;
            height: 20px;
            background: #444;
            border-radius: 4px;
            margin-top: -2px;
        }

        .bulb {
            width: 60px;
            height: 80px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 50% 50% 45% 45%;
            margin-top: -5px;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        .filament {
            width: 30px;
            height: 30px;
            border: 2px solid transparent;
            border-top: 2px solid var(--brand-yellow);
            border-radius: 50%;
            opacity: 0.1;
        }

        .active .filament {
            opacity: 1;
            box-shadow: 0 -5px 15px var(--brand-yellow);
            animation: flicker 4s linear infinite;
        }

        .active .bulb {
            background: rgba(248, 184, 3, 0.05);
            border-color: rgba(248, 184, 3, 0.3);
            box-shadow: inset 0 0 20px rgba(248, 184, 3, 0.1);
        }

        .btn-premium {
            background: var(--brand-yellow);
            color: #000;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(248, 184, 3, 0.3);
        }

        .btn-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(248, 184, 3, 0.5);
            filter: brightness(1.1);
        }
    </style>
</head>
<body class="flex flex-col items-center justify-center min-h-screen p-6">
    
    <!-- Branding -->
    <div class="absolute top-8 left-8">
        <img src="{{ asset('img/brand-logo-dark.png') }}" alt="Claesen Logo" class="h-10 opacity-80">
    </div>

    <!-- Visual -->
    <div class="wire-container active mb-8">
        <div class="wire flex flex-col items-center">
            <div class="bulb-holder"></div>
            <div class="bulb">
                <div class="filament"></div>
            </div>
        </div>
        <!-- Absolute sparks -->
        <div class="spark flicker" style="top: 150px; left: 50%; margin-left: -5px;"></div>
    </div>

    <!-- Content -->
    <div class="text-center max-w-lg">
        <h1 class="text-6xl font-bold mb-4 glow flicker">
            @if($locale === 'nl')
                404 - Kortsluiting!
            @else
                404 - Short Circuit!
            @endif
        </h1>
        
        <p class="text-xl text-gray-400 mb-8">
            @if($locale === 'nl')
                Oeps! Deze pagina zit zonder stroom. De verbinding is onderbroken of de zekering is gesprongen.
            @else
                Oops! This page is out of power. The connection was interrupted or the fuse has blown.
            @endif
        </p>

        <a href="{{ url('/') }}" class="btn-premium inline-block px-8 py-3 rounded-full font-bold text-lg uppercase tracking-wider">
            @if($locale === 'nl')
                Terug naar de Hub
            @else
                Back to the Hub
            @endif
        </a>
    </div>

    <!-- Decorative background elements -->
    <div class="absolute bottom-0 right-0 p-12 opacity-5 pointer-events-none">
        <svg width="400" height="400" viewBox="0 0 100 100" fill="none" stroke="currentColor">
            <path d="M10,50 L90,50 M50,10 L50,90 M30,30 L70,70 M70,30 L30,70" stroke-width="0.5"/>
            <circle cx="50" cy="50" r="40" stroke-width="0.5"/>
        </svg>
    </div>

</body>
</html>


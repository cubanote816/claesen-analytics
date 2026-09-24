<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('core::knx.title') }}</title>
    {{--
        CLA-602 (Fase 1): página standalone, fuera del shell de Filament a
        propósito — "debe lucir como una app nueva". Identidad tomada de
        electrobertels.md (naranja #EE7203/negro #121212, IBM Plex Sans +
        Merriweather, bordes gruesos, sombra dura sin difuminar) — mismo
        sistema de diseño que las pantallas del mockup "Installatiedossier".
        Sin Vite/theme.css del panel: esta vista nunca comparte layout con
        Filament, así que sus utilidades no aplican ni hacen falta aquí.
    --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=Merriweather:wght@900&display=swap" rel="stylesheet">
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: 'IBM Plex Sans', system-ui, sans-serif;
            color: #121212;
            background: #F4F4F2;
        }
        a { color: #EE7203; }
        a:hover { color: #CC5E00; }
        *:focus-visible { outline: 3px solid #EE7203; outline-offset: 2px; }

        .knx-accent-bar { height: 6px; background: #EE7203; flex: none; }

        .knx-header {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 14px 20px;
            border-bottom: 2px solid #121212;
            background: #FFFFFF;
            flex: none;
        }

        .knx-mark {
            width: 28px;
            height: 28px;
            background: #121212;
            display: grid;
            place-items: center;
            flex: none;
        }

        .knx-mark span {
            width: 11px;
            height: 11px;
            background: #EE7203;
        }

        .knx-wordmark {
            font-family: 'Merriweather', Georgia, serif;
            font-weight: 900;
            font-size: 16px;
        }

        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 24px;
        }

        .knx-card {
            max-width: 30rem;
            width: 100%;
            background: #FFFFFF;
            border: 2px solid #121212;
            box-shadow: 4px 4px 0px #121212;
            padding: 32px 36px;
        }

        .knx-eyebrow {
            font-size: 11.5px;
            font-weight: 600;
            letter-spacing: 0.9px;
            text-transform: uppercase;
            color: #6B6B6B;
        }

        .knx-heading {
            margin: 6px 0 14px;
            font-family: 'Merriweather', Georgia, serif;
            font-weight: 900;
            font-size: 26px;
            line-height: 1.25;
        }

        .knx-body {
            margin: 0 0 28px;
            font-size: 14px;
            line-height: 1.6;
            color: #4A4A4A;
        }

        .knx-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border: 2px solid #121212;
            background: #FFFFFF;
            color: #121212;
            font-weight: 600;
            font-size: 13.5px;
            text-decoration: none;
        }

        .knx-back:hover {
            background: #EAEAEA;
            color: #121212;
        }
    </style>
</head>
<body>
    <div class="knx-accent-bar"></div>

    <header class="knx-header">
        <div class="knx-mark" aria-hidden="true"><span></span></div>
        <div class="knx-wordmark">{{ __('core::knx.title') }}</div>
    </header>

    <main>
        <div class="knx-card">
            <div class="knx-eyebrow">{{ __('core::knx.eyebrow') }}</div>
            <h1 class="knx-heading">{{ __('core::knx.heading') }}</h1>
            <p class="knx-body">{{ __('core::knx.body') }}</p>
            <a href="{{ url('/bertels') }}" class="knx-back">&larr; {{ __('core::knx.back_to_panel') }}</a>
        </div>
    </main>
</body>
</html>

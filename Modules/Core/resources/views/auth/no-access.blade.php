<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>Geen toegang — CAFCA</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Outfit', system-ui, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 2rem;
            width: 100%;
            max-width: 420px;
            text-align: center;
        }
        .logo { margin-bottom: 1.5rem; color: #00aeef; font-size: 1.25rem; font-weight: 700; }
        .icon { font-size: 2rem; margin-bottom: 1rem; }
        h1 { font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem; }
        p { font-size: 0.875rem; color: #94a3b8; margin-bottom: 0.75rem; line-height: 1.5; }
        button {
            width: 100%;
            padding: 0.625rem;
            background: transparent;
            color: #e2e8f0;
            font-weight: 600;
            border: 1px solid #475569;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.875rem;
            margin-top: 1rem;
        }
        button:hover { border-color: #00aeef; color: #00aeef; }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">CAFCA Intelligence Hub</div>
    <div class="icon">🔒</div>
    <h1>Nog geen toegang</h1>
    <p>Uw account (<strong>{{ auth()->user()->email }}</strong>) is geregistreerd, maar heeft momenteel geen toegang tot dit systeem.</p>
    <p>Neem contact op met uw beheerder als u denkt dat dit niet klopt.</p>

    <form method="POST" action="{{ route('filament.admin.auth.logout') }}">
        @csrf
        <button type="submit">Afmelden</button>
    </form>
</div>
</body>
</html>

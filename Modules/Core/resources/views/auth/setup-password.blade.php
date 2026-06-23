<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>Wachtwoord instellen — CAFCA</title>
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
            max-width: 400px;
        }
        .logo { text-align: center; margin-bottom: 1.5rem; color: #00aeef; font-size: 1.25rem; font-weight: 700; }
        h1 { font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem; }
        p { font-size: 0.875rem; color: #94a3b8; margin-bottom: 1.5rem; }
        label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.375rem; }
        input[type="password"] {
            width: 100%;
            padding: 0.625rem 0.75rem;
            background: #0f172a;
            border: 1px solid #475569;
            border-radius: 8px;
            color: #e2e8f0;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }
        input[type="password"]:focus { outline: none; border-color: #00aeef; }
        .error { color: #f87171; font-size: 0.75rem; margin-top: -0.75rem; margin-bottom: 0.75rem; }
        button {
            width: 100%;
            padding: 0.625rem;
            background: #00aeef;
            color: #0f172a;
            font-weight: 700;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.875rem;
        }
        button:hover { background: #0284c7; }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">CAFCA Intelligence Hub</div>
    <h1>Wachtwoord instellen</h1>
    <p>Uw account is aangemaakt door een beheerder. Stel een wachtwoord in om toegang te krijgen tot het platform.</p>

    @if ($errors->any())
        <div class="error" style="margin-bottom:1rem">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('auth.setup-password.store') }}">
        @csrf
        <label for="password">Nieuw wachtwoord</label>
        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
        @error('password') <div class="error">{{ $message }}</div> @enderror

        <label for="password_confirmation">Wachtwoord bevestigen</label>
        <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">

        <button type="submit">Wachtwoord instellen</button>
    </form>
</div>
</body>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ trans('prospects::resource.unsubscribe.success_title') }} | Claesen Verlichting</title>
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
        .accent-gradient {
            background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full glass-card rounded-3xl p-8 text-center space-y-6">
        <!-- Company Logo -->
        <div class="flex justify-center mb-8">
            <img src="{{ asset('img/brand-logo-dark.png') }}" alt="Claesen Outdoor Lighting" class="h-12 w-auto">
        </div>

        <!-- Action Icon -->
        <div class="flex justify-center mb-6">
            <div class="w-16 h-16 accent-gradient rounded-2xl flex items-center justify-center shadow-lg transform rotate-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </div>
        </div>

        @if($completed)
            <h1 class="text-3xl font-semibold text-white">
                {{ trans('prospects::resource.unsubscribe.success_title') }}
            </h1>
            <p class="text-slate-400 text-lg">
                {{ trans('prospects::resource.unsubscribe.success_body') }}
            </p>
            <div class="pt-4">
                <a href="https://claesen-verlichting.be" class="text-amber-500 hover:text-amber-400 transition-colors font-medium">
                    &larr; claesen-verlichting.be
                </a>
            </div>
        @else
            <h1 class="text-3xl font-semibold text-white">
                {{ trans('prospects::resource.unsubscribe.link') }}
            </h1>
            <p class="text-slate-400 text-lg">
                {{ trans('prospects::resource.unsubscribe.text') }}
            </p>
            <form action="{{ route('prospects.unsubscribe.confirm', ['prospect' => $prospect->id, 'token' => $token]) }}" method="POST" class="pt-4">
                @csrf
                <button type="submit" class="w-full py-4 px-6 accent-gradient text-black font-semibold rounded-2xl hover:opacity-90 transition-all shadow-xl active:scale-95">
                    {{ trans('prospects::resource.unsubscribe.confirmation_button') }}
                </button>
            </form>
        @endif
    </div>
</body>
</html>

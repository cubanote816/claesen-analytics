<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Herinnering: Veiligheidsinspectie vereist</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .header { background-color: #1e3a5f; padding: 32px 40px; }
        .header h1 { color: #ffffff; margin: 0; font-size: 20px; font-weight: 600; }
        .body { padding: 32px 40px; color: #333333; line-height: 1.6; }
        .body p { margin: 0 0 16px; }
        .alert-box { background-color: #fff8e1; border-left: 4px solid #f59e0b; padding: 16px 20px; margin: 24px 0; border-radius: 4px; }
        .alert-box p { margin: 0; font-size: 15px; }
        .cta { text-align: center; margin: 32px 0; }
        .cta a { display: inline-block; background-color: #1e3a5f; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 6px; font-size: 15px; font-weight: 600; }
        .footer { background-color: #f0f0f0; padding: 20px 40px; text-align: center; font-size: 12px; color: #888888; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Claesen Outdoor Lighting Platform</h1>
    </div>
    <div class="body">
        <p>Dag {{ $recipient->name }},</p>

        @if ($daysSinceLastInspection !== null)
            <div class="alert-box">
                <p>Het is al <strong>{{ $daysSinceLastInspection }} dagen</strong> geleden dat u een veiligheidsinspectie heeft uitgevoerd.</p>
            </div>
            <p>Maandelijkse werkplekinspecties zijn een vereiste van uw functie. Voer zo snel mogelijk een nieuwe inspectie uit.</p>
        @else
            <div class="alert-box">
                <p>U heeft nog <strong>geen veiligheidsinspectie</strong> uitgevoerd.</p>
            </div>
            <p>Maandelijkse werkplekinspecties zijn een vereiste van uw functie. Start uw eerste inspectie via de Safety-app.</p>
        @endif

        @if (config('safety.pwa_url'))
            <div class="cta">
                <a href="{{ config('safety.pwa_url') }}">Inspectie starten &rarr;</a>
            </div>
        @endif

        <p>Met vriendelijke groeten,<br>Claesen Outdoor Lighting Platform</p>
    </div>
    <div class="footer">
        Dit is een automatisch bericht van het Claesen Outdoor Lighting Platform systeem. Gelieve niet op dit e-mailadres te antwoorden.
    </div>
</div>
</body>
</html>

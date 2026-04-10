<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Inter', -apple-system, sans-serif; background-color: #0f172a; color: #f8fafc; padding: 20px; }
        .wrapper { max-width: 600px; margin: 0 auto; background-color: #1e293b; border-radius: 12px; overflow: hidden; border: 1px solid #334155; border-top: 6px solid #ef4444; }
        .header { background-color: #0f172a; padding: 25px; text-align: center; color: white; }
        .header h1 { margin: 0; font-size: 18px; text-transform: uppercase; letter-spacing: 2px; color: #ef4444; }
        .body { padding: 30px; }
        .alert-box { background-color: #7f1d1d; border: 1px solid #ef4444; padding: 20px; border-radius: 8px; margin-bottom: 25px; color: #fef2f2; }
        .alert-title { font-weight: bold; font-size: 18px; margin-bottom: 10px; display: block; }
        .project-details { background-color: #111827; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .detail-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
        .detail-label { color: #94a3b8; }
        .detail-value { color: #f8fafc; font-weight: 600; }
        .footer { padding: 20px; text-align: center; font-size: 11px; color: #64748b; background-color: #111827; }
        .btn { display: inline-block; background-color: #ef4444; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <img src="https://www.claesen-verlichting.be/v1/assets/brand-logo-dark.png" alt="Claesen Logo" style="height: 50px; width: auto; margin-bottom: 15px;">
            <h1>🚨 VANGUARD ALERT</h1>
        </div>
        <div class="body">
            <div class="alert-box">
                <span class="alert-title">Kritiek Financieel Risico Gedetecteerd</span>
                Dit project heeft het veiligheidslimiet van <b>€ {{ number_format(env('WATCHDOG_IMMEDIATE_THRESHOLD', 20000), 0, ',', '.') }}</b> overschreden. Onmiddellijke actie is vereist.
            </div>

            <div class="project-details">
                <div class="detail-row">
                    <span class="detail-label">Project:</span>
                    <span class="detail-value">{{ $projectData['id'] }} - {{ $projectData['name'] ?? 'Unknown' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Huidige WIP (Onderhanden Werk):</span>
                    <span class="detail-value" style="color: #ef4444;">€ {{ number_format($projectData['wip'], 2, ',', '.') }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Dagen sinds laatste factuur:</span>
                    <span class="detail-value">{{ $projectData['stale_days'] }} dagen</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Dringendheidsniveau:</span>
                    <span class="detail-value" style="background: #ef4444; color: white; padding: 0 5px; border-radius: 3px;">{{ $projectData['risk_level'] }}</span>
                </div>
            </div>

            <p style="color: #cbd5e1; font-size: 14px; line-height: 1.6;">
                De AI heeft vastgesteld dat dit project aanzienlijke middelen verbruikt zonder de bijbehorende facturatie. Controleer onmiddellijk de status van de facturen en de urenstaten.
            </p>

            <div style="text-align: center; margin-top: 30px;">
                <a href="#" class="btn">PROJECTDETAILS BEKIJKEN</a>
            </div>
        </div>
        <div class="footer">
            Dit is een <b>onmiddellijke veiligheidswaarschuwing</b> van de Claesen Intelligence Hub.<br>
            Geen verdere onmiddellijke waarschuwingen zullen voor dit project worden verzonden tot het wekelijkse rapport.
        </div>
    </div>
</body>
</html>

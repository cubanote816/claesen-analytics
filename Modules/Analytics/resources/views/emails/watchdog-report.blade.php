<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Inter', -apple-system, sans-serif; background-color: #0f172a; color: #f8fafc; padding: 20px; }
        .wrapper { max-width: 600px; margin: 0 auto; background-color: #1e293b; border-radius: 12px; overflow: hidden; border: 1px solid #334155; border-top: 4px solid #ef4444; }
        .header { background-color: #0f172a; padding: 25px; text-align: center; color: white; }
        .header h1 { margin: 0; font-size: 16px; text-transform: uppercase; letter-spacing: 2px; color: #cbd5e1; }
        .body { padding: 30px; }
        .intro { font-size: 16px; line-height: 1.5; color: #cbd5e1; margin-bottom: 25px; }
        .project-card { background-color: #0f172a; padding: 15px; border-radius: 8px; border-left: 4px solid #facc15; margin-bottom: 12px; }
        .project-title { font-weight: bold; font-size: 14px; margin-bottom: 4px; color: #f8fafc; }
        .project-meta { font-size: 12px; color: #94a3b8; display: block; }
        .project-footer { margin-top: 10px; display: flex; justify-content: space-between; align-items: center; }
        .amount { color: #facc15; font-weight: 600; font-size: 15px; }
        .action { font-size: 11px; padding: 2px 8px; background-color: #334155; border-radius: 4px; color: #f8fafc; text-transform: uppercase; font-weight: bold; }
        .fallback { white-space: pre-wrap; line-height: 1.6; font-size: 14px; color: #cbd5e1; }
        .footer { padding: 20px; text-align: center; font-size: 11px; color: #64748b; background-color: #111827; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <img src="https://www.claesen-verlichting.be/v1/assets/brand-logo-dark.png" alt="Claesen Logo" style="height: 40px; width: auto; margin-bottom: 10px;">
            <h1>🛡️ Claesen Watchdog</h1>
        </div>
        <div class="body">
            @if(is_array($report))
                <div class="intro">
                    {{ $report['greeting'] }}<br><br>
                    {{ $report['intro'] }}
                </div>

                @if(!empty($report['risky_projects']))
                    @foreach($report['risky_projects'] as $project)
                        <div class="project-card">
                            <div class="project-title">{{ $project['id'] }} - {{ $project['name'] }}</div>
                            <span class="project-meta">Te factureren / WIP status:</span>
                            <div class="project-footer">
                                <span class="amount">{{ $project['wip'] ?? $project['wip_amount'] ?? '€ 0,00' }}</span>
                                <span class="action">{{ $project['action'] }}</span>
                            </div>
                        </div>
                    @endforeach
                @endif

                <div class="intro" style="margin-top: 20px; font-size: 14px;">
                    {{ $report['footer'] }}
                </div>
            @else
                <div class="fallback">{{ $report }}</div>
            @endif
        </div>
        <div class="footer">
            Dit is een automatisch gegenereerd rapport door <b>Claesen Intelligence Hub</b>.<br>
            Antwoorden op deze e-mail worden niet verwerkt.
        </div>
    </div>
</body>
</html>

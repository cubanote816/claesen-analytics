<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .header { background-color: #1e3a5f; padding: 32px 40px; }
        .header h1 { color: #ffffff; margin: 0; font-size: 20px; font-weight: 600; }
        .body { padding: 32px 40px; color: #333333; line-height: 1.6; }
        .body p { margin: 0 0 16px; }
        .info-table { width: 100%; border-collapse: collapse; margin: 24px 0; }
        .info-table td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; font-size: 14px; }
        .info-table td:first-child { color: #6b7280; width: 40%; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-incident { background-color: #fee2e2; color: #991b1b; }
        .badge-inspection { background-color: #d1fae5; color: #065f46; }
        .footer { background-color: #f0f0f0; padding: 20px 40px; text-align: center; font-size: 12px; color: #888888; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Claesen Outdoor Lighting Platform</h1>
    </div>
    <div class="body">
        <p>Geachte,</p>

        @if ($inspection->type === 'incident')
            <p>Er is zojuist een <strong>incidentenrapport</strong> ingediend. Het PDF-rapport is bijgevoegd.</p>
        @else
            <p>Er is zojuist een <strong>werkplekinspectie</strong> ingediend. Het PDF-rapport is bijgevoegd.</p>
        @endif

        <table class="info-table">
            <tr>
                <td>Type</td>
                <td>
                    @if ($inspection->type === 'incident')
                        <span class="badge badge-incident">Incident</span>
                    @else
                        <span class="badge badge-inspection">Inspectie</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td>Project</td>
                <td>{{ $inspection->project_id }}</td>
            </tr>
            <tr>
                <td>Ingediend door</td>
                <td>{{ $inspector->name }}</td>
            </tr>
            <tr>
                <td>Datum</td>
                <td>{{ $inspection->completed_at?->format('d/m/Y H:i') }}</td>
            </tr>
            @if ($inspection->type === 'inspection')
            <tr>
                <td>Checklist</td>
                <td>{{ $inspection->checklist->name ?? '—' }}</td>
            </tr>
            @endif
        </table>

        <p>Met vriendelijke groeten,<br>Claesen Outdoor Lighting Platform</p>
    </div>
    <div class="footer">
        Dit is een automatisch bericht van het Claesen Outdoor Lighting Platform systeem.
    </div>
</div>
</body>
</html>

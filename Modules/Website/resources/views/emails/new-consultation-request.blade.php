<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Consultation Request</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
        .wrapper { max-width: 600px; margin: 32px auto; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
        .header { background: #1a1a2e; color: #fff; padding: 24px 32px; }
        .header h1 { margin: 0; font-size: 18px; font-weight: 600; }
        .header p { margin: 4px 0 0; font-size: 12px; color: #aaa; }
        .body { padding: 28px 32px; }
        .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #888; letter-spacing: .6px; margin: 20px 0 10px; border-bottom: 1px solid #eee; padding-bottom: 6px; }
        .section-title:first-child { margin-top: 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 6px 0; vertical-align: top; }
        td.label { width: 36%; color: #666; font-size: 13px; }
        td.value { color: #111; font-size: 13px; }
        .message-box { background: #f9f9f9; border-left: 3px solid #1a1a2e; padding: 12px 16px; font-size: 13px; line-height: 1.6; color: #333; border-radius: 0 4px 4px 0; white-space: pre-wrap; }
        .cta { text-align: center; margin: 28px 0 4px; }
        .cta a { background: #1a1a2e; color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 5px; font-size: 14px; font-weight: 600; display: inline-block; }
        .footer { padding: 16px 32px; background: #f5f5f5; font-size: 11px; color: #aaa; text-align: center; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>New Consultation Request</h1>
        <p>Claesen Verlichting — CAFCA Intelligence Hub</p>
    </div>
    <div class="body">

        <div class="section-title">Contact</div>
        <table>
            <tr>
                <td class="label">Name</td>
                <td class="value">{{ $consultation->name }}</td>
            </tr>
            <tr>
                <td class="label">Email</td>
                <td class="value"><a href="mailto:{{ $consultation->email }}">{{ $consultation->email }}</a></td>
            </tr>
            <tr>
                <td class="label">Phone</td>
                <td class="value">{{ $consultation->phone ?: '—' }}</td>
            </tr>
            <tr>
                <td class="label">Company</td>
                <td class="value">{{ $consultation->company ?: '—' }}</td>
            </tr>
        </table>

        <div class="section-title">Request details</div>
        <table>
            <tr>
                <td class="label">Type</td>
                <td class="value">{{ ucfirst($consultation->type ?? '—') }}</td>
            </tr>
            <tr>
                <td class="label">Project type</td>
                <td class="value">{{ ucfirst($consultation->project_type ?? '—') }}</td>
            </tr>
            <tr>
                <td class="label">Priority</td>
                <td class="value">{{ ucfirst($consultation->priority ?? 'medium') }}</td>
            </tr>
            <tr>
                <td class="label">Source</td>
                <td class="value">{{ $consultation->source ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Preferred contact</td>
                <td class="value">{{ ucfirst($consultation->preferred_contact ?? 'email') }}</td>
            </tr>
        </table>

        <div class="section-title">Message</div>
        <div class="message-box">{{ $consultation->message }}</div>

        <div class="cta">
            <a href="{{ \App\Filament\Clusters\Website\Resources\ConsultationRequestResource::getUrl('view', ['record' => $consultation], panel: 'admin') }}">
                View in admin panel
            </a>
        </div>

    </div>
    <div class="footer">
        This notification was sent automatically by CAFCA Intelligence Hub.
    </div>
</div>
</body>
</html>

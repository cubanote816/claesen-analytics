{{-- KNX-54: one immediate notice, or the digest of what accumulated. --}}
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>{{ config('app.name') }}</title>
</head>
<body style="font-family: sans-serif; color: #1f2937;">
    <h2 style="margin: 0 0 4px;">
        {{ $immediate ? 'Melding' : 'Overzicht meldingen' }}
    </h2>

    <table cellpadding="6" style="border-collapse: collapse; width: 100%;">
        <thead>
        <tr style="text-align: left; border-bottom: 1px solid #e5e7eb;">
            <th>Soort</th>
            <th>Project</th>
            <th>Details</th>
            <th>Door</th>
            <th>Werk</th>
            <th>Aankomst</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($notices as $notice)
            <tr style="border-bottom: 1px solid #f3f4f6;">
                <td>{{ $notice->title ?? $notice->kind }}</td>
                <td>{{ $notice->project?->code }}</td>
                <td>{{ $notice->summary }}</td>
                <td>{{ $notice->reportedBy?->shortName() }}</td>
                <td>{{ $notice->captured_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                <td>{{ $notice->reported_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    @php($kantoor = rtrim((string) config('knx.kantoor_domain'), '/'))
    <p style="margin-top: 16px;">
        <a href="https://{{ $kantoor }}">Openen in Kantoor</a>
    </p>
</body>
</html>

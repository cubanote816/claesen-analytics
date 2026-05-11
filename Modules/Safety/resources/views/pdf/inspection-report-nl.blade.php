<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Werkplekinspectie Rapport</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #ccc; padding-bottom: 10px; }
        .details { margin-bottom: 20px; }
        .details table { width: 100%; border-collapse: collapse; }
        .details th, .details td { text-align: left; padding: 5px; border-bottom: 1px solid #eee; }
        .questions { width: 100%; border-collapse: collapse; }
        .questions th, .questions td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        .questions th { background-color: #f5f5f5; }
        .status-ok { color: green; font-weight: bold; }
        .status-nok { color: red; font-weight: bold; }
        .status-na { color: gray; }
        .footer { position: fixed; bottom: -20px; left: 0px; right: 0px; text-align: center; font-size: 10px; color: #999; }
    </style>
</head>
<body>

    <div class="header">
        <h1>Werkplekinspectie Rapport</h1>
    </div>

    <div class="details">
        <table>
            <tr>
                <th>Project ID:</th>
                <td>{{ $inspection->project_id }}</td>
                <th>Inspecteur:</th>
                <td>{{ $user->name ?? 'Onbekend' }}</td>
            </tr>
            <tr>
                <th>Checklist:</th>
                <td>{{ $inspection->checklist->name ?? 'N/A' }}</td>
                <th>Datum en Tijd:</th>
                <td>{{ $inspection->completed_at ? $inspection->completed_at->format('d-m-Y H:i:s') : now()->format('d-m-Y H:i:s') }}</td>
            </tr>
        </table>
    </div>

    <table class="questions">
        <thead>
            <tr>
                <th>Nr.</th>
                <th>Inspectiepunt</th>
                <th>Status</th>
                <th>Opmerking</th>
            </tr>
        </thead>
        <tbody>
            @foreach($inspection->answers as $index => $answer)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $answer->question->text_nl ?? 'Onbekende vraag' }}</td>
                <td>
                    @if($answer->status === 'ok')
                        <span class="status-ok">Akkoord (OK)</span>
                    @elseif($answer->status === 'nok')
                        <span class="status-nok">Niet Akkoord (NOK)</span>
                    @else
                        <span class="status-na">Niet van Toepassing (N/A)</span>
                    @endif
                </td>
                <td>{{ $answer->remark ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Dit document is automatisch gegenereerd. Claesen Verlichting
    </div>

</body>
</html>

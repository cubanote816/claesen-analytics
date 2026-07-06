<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>{{ $inspection->type === 'incident' ? 'Incidentenrapport' : 'Werkplekinspectie Rapport' }}</title>
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

    @php
        $logoPath = public_path('img/brand-logo-light.png');
        $logoB64  = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;
    @endphp

    <div class="header">
        @if($logoB64)
            <img src="data:image/png;base64,{{ $logoB64 }}" style="height:45px;margin-bottom:8px;">
        @endif
        <h1>{{ $inspection->type === 'incident' ? 'Incidentenrapport' : 'Werkplekinspectie Rapport' }}</h1>
    </div>

    <div class="details">
        <table>
            <tr>
                <th>Project ID:</th>
                <td>{{ $inspection->project_id }}</td>
                <th>{{ $inspection->type === 'incident' ? 'Gemeld door' : 'Inspecteur' }}:</th>
                <td>{{ $user->name ?? 'Onbekend' }}</td>
            </tr>
            @if($inspection->type === 'incident' && $inspection->incidentWorker)
            <tr>
                <th>Betrokken Medewerker:</th>
                <td colspan="3">{{ $inspection->incidentWorker->name }}</td>
            </tr>
            @endif
            @if($inspection->type === 'inspection' && $inspection->presentWorkers->isNotEmpty())
            <tr>
                <th>Aanwezige medewerkers:</th>
                <td colspan="3">{{ $inspection->presentWorkers->pluck('name')->join(', ') }}</td>
            </tr>
            @endif
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
                <th>{{ $inspection->type === 'incident' ? 'Vraag / Item' : 'Inspectiepunt' }}</th>
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
        Dit document is automatisch gegenereerd door het Claesen Outdoor Lighting Platform.
    </div>

    @php $photoAnswers = $inspection->answers->whereNotNull('photo_path')->values(); @endphp

    @if($photoAnswers->count() > 0)
    <div style="page-break-before: always;"></div>

    {{-- Section header --}}
    <div style="border-bottom: 2px solid #2c3e50; padding-bottom: 8px; margin-bottom: 18px;">
        <h2 style="margin: 0; font-size: 15px; color: #2c3e50; letter-spacing: 0.4px; font-family: sans-serif;">
            BIJLAGEN — FOTO-DOCUMENTATIE
        </h2>
        <p style="margin: 3px 0 0 0; font-size: 9px; color: #7f8c8d; font-family: sans-serif;">
            {{ $photoAnswers->count() }} foto{{ $photoAnswers->count() > 1 ? "'s" : '' }} bijgevoegd aan dit rapport
        </p>
    </div>

    {{-- 2-column grid via table — page-break-inside:avoid is reliable on <tr> in DomPDF --}}
    <table width="100%" cellspacing="0" cellpadding="0"
           style="border-collapse: separate; border-spacing: 10px 10px;">

        @foreach($photoAnswers->chunk(2) as $pair)
        <tr style="page-break-inside: avoid;">

            @foreach($pair as $answer)
            @php
                $disk  = \Illuminate\Support\Facades\Storage::disk(config('safety.disk', 'local'));
                $ext   = strtolower(pathinfo($answer->photo_path, PATHINFO_EXTENSION));
                $mime  = match($ext) {
                    'png'  => 'image/png',
                    'gif'  => 'image/gif',
                    'webp' => 'image/webp',
                    default => 'image/jpeg',
                };
                $b64  = $disk->exists($answer->photo_path)
                      ? base64_encode($disk->get($answer->photo_path))
                      : null;
                $qNum = $inspection->answers->search(fn($a) => $a->id === $answer->id) + 1;

                [$accent, $badgeBg, $badgeLabel] = match($answer->status) {
                    'nok'   => ['#c62828', '#c62828', 'NOK'],
                    'na'    => ['#78909c', '#78909c', 'N/A'],
                    default => ['#2e7d32', '#2e7d32', 'OK'],
                };
            @endphp

            <td width="50%" style="vertical-align: top; padding: 0;">
                <div style="border: 1px solid #d0d7de;
                            border-left: 4px solid {{ $accent }};
                            background: #ffffff;
                            font-family: sans-serif;">

                    {{-- Card header: question number pill + truncated text + status badge --}}
                    <div style="background: #f6f8fa;
                                border-bottom: 1px solid #d0d7de;
                                padding: 7px 9px;">
                        <table width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td style="vertical-align: top;">
                                    <span style="display: inline-block;
                                                 background: {{ $accent }};
                                                 color: #fff;
                                                 font-size: 8px;
                                                 font-weight: bold;
                                                 padding: 2px 5px;
                                                 margin-right: 4px;
                                                 vertical-align: middle;">{{ $qNum }}</span><!--
                                 --><span style="font-size: 10px;
                                                font-weight: bold;
                                                color: #24292f;
                                                line-height: 1.3;
                                                vertical-align: middle;">{{ \Illuminate\Support\Str::limit($answer->question->text_nl, 75) }}</span>
                                </td>
                                <td width="32" style="text-align: right; vertical-align: top; padding-left: 4px;">
                                    <span style="display: inline-block;
                                                 background: {{ $badgeBg }};
                                                 color: #fff;
                                                 font-size: 7.5px;
                                                 font-weight: bold;
                                                 padding: 2px 5px;
                                                 letter-spacing: 0.3px;">{{ $badgeLabel }}</span>
                                </td>
                            </tr>
                        </table>
                    </div>

                    {{-- Fixed-height image container — consistent across portrait/landscape/square --}}
                    <div style="background: #f0f2f5;
                                text-align: center;
                                height: 195px;
                                padding: 8px;">
                        @if($b64)
                            <img src="data:{{ $mime }};base64,{{ $b64 }}"
                                 style="max-width: 100%;
                                        max-height: 179px;
                                        width: auto;
                                        height: auto;">
                        @else
                            <p style="color: #9e9e9e; font-size: 9px;
                                      margin-top: 85px; font-family: sans-serif;">
                                Foto niet beschikbaar
                            </p>
                        @endif
                    </div>

                    {{-- Remark — only rendered if present --}}
                    @if($answer->remark)
                    <div style="padding: 5px 9px;
                                border-top: 1px solid #d0d7de;
                                background: #fffdf4;">
                        <span style="font-size: 8.5px; font-weight: bold;
                                     color: #6e7781; text-transform: uppercase;
                                     letter-spacing: 0.3px;">Opmerking: </span>
                        <span style="font-size: 9.5px; color: #444d56;
                                     font-style: italic;">{{ $answer->remark }}</span>
                    </div>
                    @endif

                </div>
            </td>
            @endforeach

            {{-- Empty cell when total photo count is odd --}}
            @if($pair->count() === 1)
            <td width="50%"></td>
            @endif

        </tr>
        @endforeach

    </table>
    @endif

</body>
</html>

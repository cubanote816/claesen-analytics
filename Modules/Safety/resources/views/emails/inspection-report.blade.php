<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <!--[if mso]><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml><![endif]-->
</head>
<body style="margin:0;padding:0;background-color:#eef2f7;font-family:Arial,Helvetica,sans-serif;">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
       style="background-color:#eef2f7;">
  <tr>
    <td align="center" style="padding:32px 16px;">

      <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0"
             style="width:100%;max-width:600px;background-color:#ffffff;">

        <!-- Accent bar: blue for inspection, red for incident -->
        <tr>
          <td style="height:5px;font-size:0;line-height:0;mso-line-height-rule:exactly;
                     background-color:{{ $inspection->type === 'incident' ? '#dc2626' : '#1e3a5f' }};">&nbsp;</td>
        </tr>

        <!-- Header: white background with brand logo -->
        <tr>
          <td style="background-color:#ffffff;padding:20px 36px;border-bottom:1px solid #e5e7eb;">
            <img src="{{ $message->embed(public_path('img/brand-logo-light.png')) }}"
                 alt="Claesen Outdoor Lighting"
                 width="155" style="height:auto;display:block;border:0;">
          </td>
        </tr>

        <!-- Hero: type + project ID + date/inspector -->
        <tr>
          <td style="background-color:{{ $inspection->type === 'incident' ? '#fef2f2' : '#f0f9ff' }};
                     padding:22px 36px 20px 36px;
                     border-left:4px solid {{ $inspection->type === 'incident' ? '#dc2626' : '#1e3a5f' }};
                     border-bottom:1px solid {{ $inspection->type === 'incident' ? '#fecaca' : '#bae6fd' }};">
            <p style="margin:0 0 4px 0;font-size:10px;font-weight:bold;letter-spacing:1.5px;
                       text-transform:uppercase;
                       color:{{ $inspection->type === 'incident' ? '#dc2626' : '#0369a1' }};">
              {{ $inspection->type === 'incident' ? 'Incidentenrapport' : 'Werkplekinspectie' }}
            </p>
            <p style="margin:0 0 8px 0;font-size:26px;font-weight:bold;color:#111827;
                       line-height:1.1;letter-spacing:-0.3px;">
              {{ $inspection->project_id }}
            </p>
            <p style="margin:0;font-size:12px;color:#6b7280;line-height:1.4;">
              {{ $inspection->completed_at?->format('d/m/Y') }}
              &nbsp;&middot;&nbsp;
              {{ $inspection->completed_at?->format('H:i') }}
              &nbsp;&middot;&nbsp;
              {{ $inspector->name }}
            </p>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:28px 36px 32px 36px;background-color:#ffffff;">

            <p style="margin:0 0 22px 0;font-size:14px;color:#374151;line-height:1.65;">
              @if($inspection->type === 'incident')
                Er is een <strong>incidentenrapport</strong> ingediend via het Claesen
                Veiligheidsplatform. Het volledige PDF&#8209;rapport is bijgevoegd aan dit bericht.
              @else
                Er is een nieuwe <strong>werkplekinspectie</strong> ingediend via het Claesen
                Veiligheidsplatform. Het volledige PDF&#8209;rapport is bijgevoegd aan dit bericht.
              @endif
            </p>

            <!-- Detail table -->
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
                   style="border:1px solid #e5e7eb;border-collapse:collapse;">

              <tr>
                <td style="width:36%;padding:11px 14px;background-color:#f9fafb;
                           border-bottom:1px solid #e5e7eb;font-size:11px;font-weight:bold;
                           color:#6b7280;text-transform:uppercase;letter-spacing:0.6px;
                           vertical-align:middle;">Type</td>
                <td style="padding:11px 14px;background-color:#f9fafb;
                           border-bottom:1px solid #e5e7eb;font-size:13px;color:#111827;
                           vertical-align:middle;">
                  @if($inspection->type === 'incident')
                    <span style="background-color:#fee2e2;color:#991b1b;font-size:11px;
                                 font-weight:bold;padding:2px 9px;">Incident</span>
                  @else
                    <span style="background-color:#d1fae5;color:#065f46;font-size:11px;
                                 font-weight:bold;padding:2px 9px;">Inspectie</span>
                  @endif
                </td>
              </tr>

              <tr>
                <td style="padding:11px 14px;border-bottom:1px solid #e5e7eb;font-size:11px;
                           font-weight:bold;color:#6b7280;text-transform:uppercase;
                           letter-spacing:0.6px;vertical-align:middle;">Project</td>
                <td style="padding:11px 14px;border-bottom:1px solid #e5e7eb;font-size:13px;
                           color:#111827;font-weight:600;vertical-align:middle;">
                  {{ $inspection->project_id }}
                </td>
              </tr>

              <tr>
                <td style="padding:11px 14px;background-color:#f9fafb;
                           border-bottom:1px solid #e5e7eb;font-size:11px;font-weight:bold;
                           color:#6b7280;text-transform:uppercase;letter-spacing:0.6px;
                           vertical-align:middle;">Ingediend door</td>
                <td style="padding:11px 14px;background-color:#f9fafb;
                           border-bottom:1px solid #e5e7eb;font-size:13px;color:#111827;
                           vertical-align:middle;">{{ $inspector->name }}</td>
              </tr>

              <tr>
                <td style="padding:11px 14px;border-bottom:1px solid #e5e7eb;font-size:11px;
                           font-weight:bold;color:#6b7280;text-transform:uppercase;
                           letter-spacing:0.6px;vertical-align:middle;">Datum</td>
                <td style="padding:11px 14px;border-bottom:1px solid #e5e7eb;font-size:13px;
                           color:#111827;vertical-align:middle;">
                  {{ $inspection->completed_at?->format('d/m/Y H:i') }}
                </td>
              </tr>

              @if($inspection->type === 'inspection')
              <tr>
                <td style="padding:11px 14px;background-color:#f9fafb;font-size:11px;
                           font-weight:bold;color:#6b7280;text-transform:uppercase;
                           letter-spacing:0.6px;vertical-align:middle;">Checklist</td>
                <td style="padding:11px 14px;background-color:#f9fafb;font-size:13px;
                           color:#111827;vertical-align:middle;">
                  {{ $inspection->checklist->name ?? '—' }}
                </td>
              </tr>
              @else
              <tr>
                <td style="padding:11px 14px;background-color:#f9fafb;font-size:11px;
                           font-weight:bold;color:#6b7280;text-transform:uppercase;
                           letter-spacing:0.6px;vertical-align:middle;">Betrokken medewerker</td>
                <td style="padding:11px 14px;background-color:#f9fafb;font-size:13px;
                           color:#111827;vertical-align:middle;">
                  {{ $inspection->incidentWorker?->name ?? '—' }}
                </td>
              </tr>
              @endif

            </table>

            <p style="margin:26px 0 0 0;font-size:13px;color:#6b7280;line-height:1.6;">
              Met vriendelijke groeten,<br>
              <strong style="color:#1e3a5f;">Claesen Outdoor Lighting</strong>
            </p>

          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background-color:#f8fafc;border-top:1px solid #e5e7eb;
                     padding:18px 36px;text-align:center;">
            <p style="margin:0;font-size:11px;color:#9ca3af;line-height:1.6;">
              Dit is een automatisch bericht van het
              <strong style="color:#6b7280;">Claesen Outdoor Lighting Platform</strong>.<br>
              Gelieve niet te antwoorden op dit e&#8209;mailadres.
            </p>
          </td>
        </tr>

      </table>

    </td>
  </tr>
</table>

</body>
</html>

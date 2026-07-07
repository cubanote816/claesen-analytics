<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Herinnering: Veiligheidsinspectie vereist</title>
    <!--[if mso]><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml><![endif]-->
</head>
<body style="margin:0;padding:0;background-color:#eef2f7;font-family:Arial,Helvetica,sans-serif;">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
       style="background-color:#eef2f7;">
  <tr>
    <td align="center" style="padding:32px 16px;">

      <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0"
             style="width:100%;max-width:600px;background-color:#ffffff;">

        <!-- Accent bar: amber for reminders -->
        <tr>
          <td style="height:5px;font-size:0;line-height:0;mso-line-height-rule:exactly;
                     background-color:#f59e0b;">&nbsp;</td>
        </tr>

        <!-- Header: white background with brand logo -->
        <tr>
          <td style="background-color:#ffffff;padding:20px 36px;border-bottom:1px solid #e5e7eb;">
            <img src="{{ $message->embed(public_path('img/brand-logo-light.png')) }}"
                 alt="Claesen Outdoor Lighting"
                 width="155" style="height:auto;display:block;border:0;">
          </td>
        </tr>

        <!-- Hero: reminder label + title -->
        <tr>
          <td style="background-color:#fffbeb;
                     padding:22px 36px 20px 36px;
                     border-left:4px solid #f59e0b;
                     border-bottom:1px solid #fde68a;">
            <p style="margin:0 0 4px 0;font-size:10px;font-weight:bold;letter-spacing:1.5px;
                       text-transform:uppercase;color:#92400e;">
              Veiligheidsherinnering
            </p>
            <p style="margin:0 0 8px 0;font-size:26px;font-weight:bold;color:#111827;
                       line-height:1.1;letter-spacing:-0.3px;">
              Inspectie vereist
            </p>
            <p style="margin:0;font-size:12px;color:#6b7280;line-height:1.4;">
              Dag {{ $recipient->name }}
            </p>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:28px 36px 32px 36px;background-color:#ffffff;">

            <!-- Alert box -->
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
                   style="background-color:#fffbeb;border-left:4px solid #f59e0b;margin-bottom:22px;">
              <tr>
                <td style="padding:14px 18px;font-size:14px;color:#78350f;line-height:1.55;">
                  @if($daysSinceLastInspection !== null)
                    Het is al <strong>{{ $daysSinceLastInspection }} dagen</strong> geleden
                    dat u een veiligheidsinspectie heeft uitgevoerd.
                  @else
                    U heeft nog <strong>geen veiligheidsinspectie</strong> uitgevoerd.
                  @endif
                </td>
              </tr>
            </table>

            <p style="margin:0 0 22px 0;font-size:14px;color:#374151;line-height:1.65;">
              @if($daysSinceLastInspection !== null)
                Maandelijkse werkplekinspecties zijn een vereiste van uw functie.
                Voer zo snel mogelijk een nieuwe inspectie uit.
              @else
                Maandelijkse werkplekinspecties zijn een vereiste van uw functie.
                Start uw eerste inspectie via de Safety&#8209;app.
              @endif
            </p>

            @if(config('safety.pwa_url'))
            <!-- CTA button -->
            <table role="presentation" cellspacing="0" cellpadding="0" border="0"
                   style="margin:0 0 28px 0;">
              <tr>
                <td style="background-color:#1e3a5f;padding:0;">
                  <a href="{{ config('safety.pwa_url') }}"
                     style="display:inline-block;background-color:#1e3a5f;color:#ffffff;
                            text-decoration:none;padding:13px 28px;font-size:14px;
                            font-weight:bold;letter-spacing:0.3px;">
                    Inspectie starten &rarr;
                  </a>
                </td>
              </tr>
            </table>
            @endif

            <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.6;">
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

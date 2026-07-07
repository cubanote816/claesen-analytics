{{--
    Encabezado corporativo oficial de Claesen (BV) — usar en TODO PDF nuevo
    que tenga carácter oficial (facturas, informes, actas, etc.), vía:
        @include('core::pdf.letterhead')
    Patrón fijado por el usuario (2026-07-06), no modificar el diseño sin
    aprobación explícita — replica el papel membretado real de la empresa.
--}}
@php
    $letterheadLogoPath = public_path('img/brand-logo-light.png');
    $letterheadLogoB64  = file_exists($letterheadLogoPath) ? base64_encode(file_get_contents($letterheadLogoPath)) : null;
@endphp

<table width="100%" cellspacing="0" cellpadding="0" style="border-collapse: collapse; font-family: sans-serif;">
    <tr>
        <td width="30%" style="vertical-align: middle; padding: 0 14px 0 0;">
            @if($letterheadLogoB64)
                <img src="data:image/png;base64,{{ $letterheadLogoB64 }}" style="height: 55px; width: auto;">
            @endif
        </td>
        <td width="23%" style="vertical-align: top; border-left: 1px solid #333; padding: 2px 12px; font-size: 8px; color: #333; line-height: 1.7;">
            <strong>KANTOOR</strong><br>
            Redemptiestraat 35<br>
            3740 Bilzen<br>
            België<br>
            Tel:+32 (89) 41.32.40
        </td>
        <td width="24%" style="vertical-align: top; border-left: 1px solid #333; padding: 2px 12px; font-size: 8px; color: #333; line-height: 1.7;">
            <strong>MAATSCHAPPELIJKE ZETEL</strong><br>
            Claesen BV<br>
            Benoit Jansenstraat 4<br>
            2490 Balen<br>
            België<br>
            BTW: BE 0413.993.228
        </td>
        <td width="23%" style="vertical-align: top; border-left: 1px solid #333; padding: 2px 12px; font-size: 8px; color: #333; line-height: 1.7;">
            info@claesen-verlichting.be<br>
            www.claesen-verlichting.be<br>
            IBAN: BE80 2350 1008 4877<br>
            BIC: GEBABEBB<br>
            RPR Antwerpen Afd. Turnhout<br>
            Reg: 413.993.228/102611-23
        </td>
    </tr>
</table>
<div style="border-bottom: 2px solid #333; margin: 10px 0 16px 0;"></div>

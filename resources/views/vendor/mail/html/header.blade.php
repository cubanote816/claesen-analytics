@props(['url'])
@php($brand = \Modules\Core\Support\MailBrand::current())
<tr>
<td class="header" bgcolor="{{ $brand['band'] }}" style="background-color: {{ $brand['band'] }}; padding: 30px 40px 26px; text-align: left;">
@if (filled($url))
<a href="{{ $url }}" style="display: inline-block; color: #ffffff; font-size: 22px; font-weight: bold; text-decoration: none;">
@endif
<img src="cid:{{ $brand['logo_cid'] }}" width="{{ $brand['logo_width'] }}" alt="{{ trim($slot) }}" style="display: block; border: 0; height: auto; width: {{ $brand['logo_width'] }}px; max-width: 100%;">
@if (filled($url))
</a>
@endif
</td>
</tr>
<tr>
<td class="stripe" bgcolor="{{ $brand['accent'] }}" height="6" style="background-color: {{ $brand['accent'] }}; height: 6px; line-height: 6px; font-size: 0;">&nbsp;</td>
</tr>

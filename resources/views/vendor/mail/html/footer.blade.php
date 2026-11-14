@php($brand = \Modules\Core\Support\MailBrand::current())
<tr>
<td class="footer-cell" bgcolor="{{ $brand['band'] }}" style="background-color: {{ $brand['band'] }}; padding: 22px 40px 26px; text-align: left;">
<p style="margin: 0; color: #ffffff; font-size: 13px; font-weight: bold; line-height: 20px;">{{ \Modules\Core\Support\MailBrand::footerName() }}</p>
<p style="margin: 0; color: #c3cedf; font-size: 13px; line-height: 20px;">{!! trim($slot) !!}</p>
</td>
</tr>

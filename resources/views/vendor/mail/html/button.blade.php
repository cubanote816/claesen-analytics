@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
@php
$brand = \Modules\Core\Support\MailBrand::current();
$brandStyle = $color === 'primary'
    ? "background-color: {$brand['accent']}; color: {$brand['button_text']};"
    : '';
@endphp
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td @if ($color === 'primary') bgcolor="{{ $brand['accent'] }}" @endif style="border-radius: 12px;">
<a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener" style="{{ $brandStyle }}">{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>

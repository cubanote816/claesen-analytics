@props(['headerUrl' => null])
{{-- CLA-707: header-url overrides the logo link (null = config('app.url'), unchanged for every staff mail; '' = no link, for mail to visitors). --}}
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="$headerUrl ?? config('app.url')">
{{ \Modules\Core\Support\MailBrand::footerName() }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ \Modules\Core\Support\MailBrand::footerName() }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>

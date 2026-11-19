{{--
    CLA-707: the visitor acknowledgement in the per-company branded layout
    (CLA-686; MailBrand::forSite() in the Mailable's envelope() picks the
    company). Markdown is parsed here, so everything the VISITOR typed (name,
    message) goes only inside single-line raw HTML blocks, escaped and with
    newlines turned into <br>: CommonMark passes such a block through untouched
    and a blank line can never end it. Keep every line flush-left: four spaces
    of indentation would turn it into a code block.
--}}
@php
    $visitorName = e($consultation->name);
    $visitorMessage = str_replace(["\r\n", "\r", "\n"], '<br>', e($consultation->message));
    $accent = \Modules\Core\Support\MailBrand::current()['accent'];
    // The visitor must never get a link to the private backoffice (config('app.url')):
    // the site's public domain, or no link at all.
    $headerUrl = filled($site->domain) ? 'https://'.$site->domain : '';
    $phoneHtml = null;
    if ($phone) {
        $phoneHtml = $phone['tel']
            ? '<a href="'.e($phone['tel']).'">'.e($phone['display']).'</a>'
            : e($phone['display']);
    }
@endphp
<x-mail::message :header-url="$headerUrl">
# {{ __('website::consultation.confirmation.subject', ['reference' => $reference]) }}

<p>{!! str_replace('__NAME__', $visitorName, e(__('website::consultation.confirmation.greeting', ['name' => '__NAME__']))) !!}</p>

{{ __('website::consultation.confirmation.received') }}

<x-mail::panel>
{{ __('website::consultation.confirmation.reference', ['reference' => $reference]) }}
</x-mail::panel>

{{ __('website::consultation.confirmation.next') }}

@if ($phoneHtml)
<p>{!! str_replace('__PHONE__', $phoneHtml, e(__('website::consultation.confirmation.urgent', ['phone' => '__PHONE__']))) !!}</p>
@endif

**{{ __('website::consultation.confirmation.your_message') }}**

<div style="margin: 0 0 16px; padding: 12px 16px; background-color: #f4f4f5; border-left: 3px solid {{ $accent }}; border-radius: 0 4px 4px 0;">{!! $visitorMessage !!}</div>

{{ __('website::consultation.confirmation.reply') }}

{{ __('website::consultation.confirmation.sign_off') }}<br>
{{ $site->organization->name }}
</x-mail::message>

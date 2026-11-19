{{-- CLA-705: plain-text part of ConsultationConfirmationMail. --}}
{!! __('website::consultation.confirmation.greeting', ['name' => $consultation->name]) !!}

{!! __('website::consultation.confirmation.received') !!}

{!! __('website::consultation.confirmation.reference', ['reference' => $reference]) !!}

{!! __('website::consultation.confirmation.next') !!}
@if ($phone)

{!! __('website::consultation.confirmation.urgent', ['phone' => $phone['display']]) !!}
@endif

{!! __('website::consultation.confirmation.your_message') !!}:

{!! $consultation->message !!}

{!! __('website::consultation.confirmation.reply') !!}

{!! __('website::consultation.confirmation.sign_off') !!}
{!! $site->organization->name !!}

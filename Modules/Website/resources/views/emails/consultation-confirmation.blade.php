{{-- CLA-705: rendered in the visitor's locale (ConsultationConfirmationMail::resolveLocale). --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('website::consultation.confirmation.subject', ['reference' => $reference]) }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
        .wrapper { max-width: 600px; margin: 32px auto; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
        .header { background: #1a1a2e; color: #fff; padding: 24px 32px; }
        .header h1 { margin: 0; font-size: 18px; font-weight: 600; }
        .header p { margin: 4px 0 0; font-size: 12px; color: #c3c3d0; }
        .body { padding: 28px 32px; font-size: 14px; line-height: 1.7; color: #333; }
        .reference { margin: 16px 0; padding: 12px 16px; background: #f1f4f9; border-radius: 4px; }
        .message { margin: 0; padding: 12px 16px; background: #f9f9f9; border-left: 3px solid #1a1a2e; border-radius: 0 4px 4px 0; white-space: pre-wrap; }
        .footer { padding: 16px 32px; background: #f5f5f5; font-size: 12px; color: #666; text-align: center; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>{{ __('website::consultation.confirmation.subject', ['reference' => $reference]) }}</h1>
        <p>{{ $site->organization->name }}</p>
    </div>
    <div class="body">
        <p>{{ __('website::consultation.confirmation.greeting', ['name' => $consultation->name]) }}</p>
        <p>{{ __('website::consultation.confirmation.received') }}</p>
        <p class="reference">{{ __('website::consultation.confirmation.reference', ['reference' => $reference]) }}</p>
        <p>{{ __('website::consultation.confirmation.next') }}</p>
        @if ($phone)
            @php($phoneHtml = $phone['tel'] ? '<a href="'.e($phone['tel']).'">'.e($phone['display']).'</a>' : e($phone['display']))
            {{-- The sentence is escaped first; only the phone placeholder becomes markup. --}}
            <p>{!! str_replace('__PHONE__', $phoneHtml, e(__('website::consultation.confirmation.urgent', ['phone' => '__PHONE__']))) !!}</p>
        @endif
        <p><strong>{{ __('website::consultation.confirmation.your_message') }}</strong></p>
        <blockquote class="message">{{ $consultation->message }}</blockquote>
        <p>{{ __('website::consultation.confirmation.reply') }}</p>
        <p>{{ __('website::consultation.confirmation.sign_off') }}<br>{{ $site->organization->name }}</p>
    </div>
    <div class="footer">{{ $site->organization->name }}</div>
</div>
</body>
</html>

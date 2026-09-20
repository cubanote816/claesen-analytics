<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>We received your request</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
        .wrapper { max-width: 600px; margin: 32px auto; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
        .header { background: #1a1a2e; color: #fff; padding: 24px 32px; }
        .header h1 { margin: 0; font-size: 18px; font-weight: 600; }
        .header p { margin: 4px 0 0; font-size: 12px; color: #aaa; }
        .body { padding: 28px 32px; font-size: 13px; line-height: 1.7; color: #333; }
        .footer { padding: 16px 32px; background: #f5f5f5; font-size: 11px; color: #aaa; text-align: center; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>We received your request</h1>
        <p>{{ $site->organization->name }}</p>
    </div>
    <div class="body">
        <p>Hi {{ $consultation->name }},</p>
        <p>
            Thank you for reaching out — we've received your message and a
            member of our team will get back to you shortly.
        </p>
        <p>For your records, here's what you sent us:</p>
        <blockquote style="margin: 0; padding: 12px 16px; background: #f9f9f9; border-left: 3px solid #1a1a2e; border-radius: 0 4px 4px 0; white-space: pre-wrap;">{{ $consultation->message }}</blockquote>
        <p>Kind regards,<br>{{ $site->organization->name }}</p>
    </div>
    <div class="footer">
        This is an automated confirmation — please do not reply directly to this e-mail.
    </div>
</div>
</body>
</html>

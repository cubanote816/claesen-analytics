<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'Claesen Outdoor Lighting' }}</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }

        .container {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            padding: 40px 20px;
            text-align: center;
        }

        .logo {
            max-width: 180px;
            height: auto;
        }

        .content {
            padding: 40px;
            color: #334155;
            line-height: 1.6;
            font-size: 16px;
        }

        .footer {
            background-color: #f1f5f9;
            padding: 30px;
            text-align: center;
            color: #64748b;
            font-size: 14px;
        }

        .button {
            display: inline-block;
            padding: 14px 28px;
            background-color: #1a56db;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 24px;
            transition: background 0.2s;
        }

        .social-links {
            margin-top: 20px;
        }

        .social-links a {
            margin: 0 10px;
            color: #64748b;
            text-decoration: none;
        }

        h1 {
            color: #0f172a;
            margin-top: 0;
        }

        hr {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 30px 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <img src="https://claesen-verlichting.be/v1/assets/brand-logo-dark.png" 
                 alt="Claesen Outdoor Lighting" 
                 width="180" 
                 border="0" 
                 class="logo"
                 style="display: block; margin: 0 auto; width: 180px; height: auto;">
        </div>

        <!-- Body -->
        <div class="content">
            {!! $body !!}
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>Claesen Outdoor Lighting</strong></p>
            <p>E: info@claesen-verlichting.be | WWW: <a href="https://claesen-verlichting.be" style="color: #1a56db;">claesen-verlichting.be</a></p>

            <div class="social-links">
                <a href="#">LinkedIn</a>
                <a href="https://www.facebook.com/profile.php?id=100086067557307">Facebook</a>
                <a href="https://claesen-verlichting.be">Website</a>
            </div>

            <hr>

            <p style="font-size: 12px; color: #94a3b8; margin-bottom: 8px;">
                {{ trans('prospects::resource.unsubscribe.text') }} 
                <a href="{{ $unsubscribe_url ?? '#' }}" style="color: #1a56db; text-decoration: underline;">
                    {{ trans('prospects::resource.unsubscribe.link') }}
                </a>
            </p>

            <p style="font-size: 12px; color: #94a3b8;">
                Deze e-mail is verzonden naar u door de Claesen Intelligence Hub.<br>
                © 2026 Claesen Verlichting. Alle rechten voorbehouden.
            </p>
        </div>
    </div>
</body>

</html>
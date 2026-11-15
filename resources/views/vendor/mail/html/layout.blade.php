<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<title>{{ \Modules\Core\Support\MailBrand::footerName() }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<style>
:root {
color-scheme: light dark;
supported-color-schemes: light dark;
}

@media only screen and (max-width: 600px) {
.inner-body {
width: 100% !important;
}

.content-cell {
padding: 28px 24px 32px !important;
}
}

@media only screen and (max-width: 500px) {
.button {
display: block !important;
width: 100% !important;
}
}

/* The header and footer bands are already dark and keep their own colors; only the
   white card follows the client's dark mode. */
@media (prefers-color-scheme: dark) {
.wrapper {
background-color: #0b0b0f !important;
}

.inner-body,
.inner-body .body-cell {
background-color: #14141b !important;
}

.inner-body .body-cell,
.inner-body .body-cell p,
.inner-body .body-cell li,
.inner-body .body-cell h1,
.inner-body .body-cell a:not(.button) {
color: #e9ecef !important;
}
}
</style>
{!! $head ?? '' !!}
</head>
<body>

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table class="inner-body" align="center" width="600" cellpadding="0" cellspacing="0" role="presentation" bgcolor="#ffffff">
{!! $header ?? '' !!}

<!-- Email Body -->
<tr>
<td class="content-cell body-cell" bgcolor="#ffffff">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>

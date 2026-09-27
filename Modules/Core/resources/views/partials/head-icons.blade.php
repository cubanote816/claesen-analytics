{{-- CLA-450: shared favicon/PWA-icon <head> partial for the ~12 standalone
     Blade views that ship their own <head> outside the Filament panel.
     Same 5 links as the panel's own set (CLA-449, AdminPanelProvider.php
     HEAD_END hook) — reuses the existing public/ files, no new assets. --}}
<link rel="icon" href="/favicon.ico" sizes="16x16 32x32 48x48 64x64 128x128 256x256">
<link rel="icon" href="/favicon.svg" type="image/svg+xml" sizes="any">
<link rel="icon" href="/favicon-32x32.png" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">
<link rel="manifest" href="/site.webmanifest">

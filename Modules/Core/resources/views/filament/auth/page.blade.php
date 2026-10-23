{{--
    Shared page view of the two-panel auth screens (Sign in today; Forgot / Reset
    password plug into it the same way). The brand panel and the stylesheet come
    from the SIMPLE_LAYOUT_START / HEAD_END hooks on every filament.*.auth.* route,
    so a page only needs:

        protected string $view = 'core::filament.auth.page';
        public function hasLogo(): bool { return false; }

    It swaps Filament's simple-page header (an <h1>, which would clash with the
    brand panel's <h1>) for the design's <h2> heading + lede. Heading and
    subheading still come from the page's getHeading()/getSubheading().
--}}
<x-filament-panels::page.simple heading="" subheading="">
    <header class="cafca-login-heading">
        <h2 class="cafca-login-heading__title">{{ $this->getHeading() }}</h2>

        @if (filled($subheading = $this->getSubheading()))
            <p class="cafca-login-heading__lede">{{ $subheading }}</p>
        @endif
    </header>

    {{ $this->content }}
</x-filament-panels::page.simple>

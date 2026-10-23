{{-- Heading block of the shared auth layout: <h2> + lede from the page's getHeading()/getSubheading().
     An Htmlable subheading (e.g. the escaped <strong>email</strong> of the forgot-password confirmation) is printed as is. --}}
<header class="cafca-login-heading">
    <h2 class="cafca-login-heading__title">{{ $this->getHeading() }}</h2>

    @if (filled($subheading = $this->getSubheading()))
        <p class="cafca-login-heading__lede">{{ $subheading }}</p>
    @endif
</header>

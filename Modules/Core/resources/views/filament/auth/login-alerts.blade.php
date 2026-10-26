{{--
    Notices above the sign-in fields (AUTH_LOGIN_FORM_BEFORE).

    The credential notice is the REAL auth validation error: Login's
    throwFailureValidationException() puts it on `data.email`, and Livewire shares
    that error bag with every view rendered during the component's render. It is
    shown here once, as a role="alert" notice; the email field's own inline copy is
    hidden by login-theme (.cafca-login-field--email).

    Plain markup + login-theme classes, never Tailwind utilities: the bertels panel
    has no Vite theme, so utilities used only here would not compile there.
--}}
@php
    $notices = array_values(array_filter([
        $errors->first('data.email'),
        $errors->first('microsoft'),
    ]));
@endphp

@foreach ($notices as $notice)
    <div class="cafca-login-alert" role="alert">
        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5"/><path d="M12 16.5h.01"/></svg>
        <span>{{ $notice }}</span>
    </div>
@endforeach

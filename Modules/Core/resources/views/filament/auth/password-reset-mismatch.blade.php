{{-- Errors of the new-password field (the real `same` rule's "The two passwords don't match." and the policy rules), shown once as alerts under the confirm field. --}}
@foreach ($errors->get('password') as $message)
    <span class="cafca-login-field-error" role="alert">{{ $message }}</span>
@endforeach

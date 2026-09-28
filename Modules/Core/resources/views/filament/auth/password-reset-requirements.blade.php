{{--
    CLA-603: the mockup's live password-requirements checklist, rendered inside
    the reset form between the two password fields and the submit button.

    The mockup colours each line as it is satisfied; Alpine reproduces that by
    listening directly on the page's password inputs. It attaches the listeners
    to the elements found at init instead of relying on event bubbling, so it
    does not matter that this block is rendered as a sibling of the inputs
    rather than an ancestor (a wrapper x-on:input would never see them).
--}}
<div
    class="cafca-login-requirements"
    x-data="{
        password: '',
        confirmation: '',
        init() {
            this.$root
                .closest('.fi-simple-main')
                ?.querySelectorAll('input[type=password]')
                .forEach((input) => {
                    const isConfirmation = (input.id || input.name || '')
                        .toLowerCase()
                        .includes('confirmation');
                    const key = isConfirmation ? 'confirmation' : 'password';

                    this[key] = input.value;
                    input.addEventListener('input', () => {
                        this[key] = input.value;
                    });
                });
        },
    }"
>
    <div class="cafca-login-requirement" :class="password.length >= 8 ? 'is-met' : ''">
        <span class="cafca-login-requirement__dot" aria-hidden="true"></span>
        {{ __('core::auth.reset_requirement_length') }}
    </div>

    <div
        class="cafca-login-requirement"
        :class="password.length > 0 && password === confirmation ? 'is-met' : ''"
    >
        <span class="cafca-login-requirement__dot" aria-hidden="true"></span>
        {{ __('core::auth.reset_requirement_match') }}
    </div>
</div>

<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * `/` is the Filament admin dashboard, so an unauthenticated visitor is
     * redirected to the login page rather than served a 200. (The stock scaffold
     * assertion of a bare 200 has never held for this app.)
     */
    public function test_the_root_route_redirects_guests_to_the_login_page(): void
    {
        $this->get('/')
            ->assertRedirect(route('filament.admin.auth.login'));
    }
}

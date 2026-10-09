<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class RegistrationDisabledTest extends TestCase
{
    public function test_registration_screen_does_not_exist(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_registration_endpoint_does_not_exist(): void
    {
        $this->post('/register', [
            'name' => 'Visitante',
            'email' => 'visitante@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();
    }

    public function test_email_verification_routes_do_not_exist(): void
    {
        $this->get('/verify-email')->assertNotFound();
    }
}

<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * La raíz no es pública: lleva al tablero, que pide iniciar sesión.
     */
    public function test_the_root_redirects_guests_to_the_login_screen(): void
    {
        $this->get('/')->assertRedirect('/dashboard');

        $this->get('/dashboard')->assertRedirect('/login');
    }
}

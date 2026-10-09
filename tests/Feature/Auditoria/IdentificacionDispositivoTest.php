<?php

namespace Tests\Feature\Auditoria;

use App\Http\Middleware\IdentificarDispositivo;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentificacionDispositivoTest extends TestCase
{
    public function test_a_browser_without_identifier_receives_a_new_persistent_one(): void
    {
        $response = $this->get('/login');

        $cookie = $response->getCookie(IdentificarDispositivo::COOKIE, decrypt: true);

        $this->assertNotNull($cookie);
        $this->assertTrue(Str::isUuid($cookie->getValue()));
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertGreaterThan(now()->addYears(4)->getTimestamp(), $cookie->getExpiresTime());
    }

    public function test_the_same_identifier_is_reused_on_later_requests(): void
    {
        $existente = (string) Str::uuid();

        $response = $this->withCookie(IdentificarDispositivo::COOKIE, $existente)->get('/login');

        $response->assertCookieMissing(IdentificarDispositivo::COOKIE);
        $this->assertSame($existente, request()->attributes->get(IdentificarDispositivo::ATRIBUTO));
    }

    public function test_an_invalid_identifier_is_replaced(): void
    {
        $response = $this->withCookie(IdentificarDispositivo::COOKIE, 'manipulado')->get('/login');

        $cookie = $response->getCookie(IdentificarDispositivo::COOKIE, decrypt: true);
        $this->assertTrue(Str::isUuid($cookie->getValue()));
    }
}

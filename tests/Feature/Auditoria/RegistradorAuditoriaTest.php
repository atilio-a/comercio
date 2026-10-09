<?php

namespace Tests\Feature\Auditoria;

use App\Http\Middleware\IdentificarDispositivo;
use App\Models\Auditoria;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\RegistradorAuditoria;
use App\Services\SucursalActiva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class RegistradorAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::factory()->create();
        $this->user = User::factory()->conSucursal($this->sucursal)->create();

        $this->actingAs($this->user);
        app(SucursalActiva::class)->establecer($this->sucursal);
        request()->attributes->set(IdentificarDispositivo::ATRIBUTO, '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d');
        request()->headers->set('User-Agent', 'Navegador de prueba');
    }

    public function test_the_record_contains_who_what_where_and_the_changed_values(): void
    {
        $afectado = User::factory()->create();

        $registro = app(RegistradorAuditoria::class)->registrar(
            'usuario.actualizado',
            $afectado,
            ['name' => 'Ana'],
            ['name' => 'Ana María'],
        );

        $registro->refresh();
        $this->assertSame($this->user->id, $registro->user_id);
        $this->assertSame($this->sucursal->id, $registro->sucursal_id);
        $this->assertSame('usuario.actualizado', $registro->accion);
        $this->assertSame(User::class, $registro->modelo);
        $this->assertSame($afectado->id, $registro->modelo_id);
        $this->assertSame(['name' => 'Ana'], $registro->antes);
        $this->assertSame(['name' => 'Ana María'], $registro->despues);
        $this->assertSame('127.0.0.1', $registro->ip);
        $this->assertSame('9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d', $registro->dispositivo);
        $this->assertSame('Navegador de prueba', $registro->user_agent);
        $this->assertNotNull($registro->created_at);
    }

    public function test_passwords_and_tokens_are_never_stored(): void
    {
        $registro = app(RegistradorAuditoria::class)->registrar(
            'usuario.actualizado',
            $this->user,
            ['password' => 'hash-anterior', 'remember_token' => 'abc'],
            ['password' => 'hash-nuevo', 'name' => 'Otro'],
        );

        $guardado = Auditoria::query()->whereKey($registro->id)->toBase()->first();
        $crudo = $guardado->antes.$guardado->despues;

        $this->assertStringNotContainsString('hash-anterior', $crudo);
        $this->assertStringNotContainsString('hash-nuevo', $crudo);
        $this->assertStringNotContainsString('abc', $crudo);
        $this->assertSame([], $registro->refresh()->antes);
        $this->assertSame(RegistradorAuditoria::VALOR_OCULTO, $registro->despues['password']);
        $this->assertSame('Otro', $registro->despues['name']);
    }

    public function test_records_cannot_be_updated(): void
    {
        $registro = app(RegistradorAuditoria::class)->registrar('prueba');

        $this->expectException(LogicException::class);

        $registro->update(['accion' => 'otra']);
    }

    public function test_records_cannot_be_deleted(): void
    {
        $registro = app(RegistradorAuditoria::class)->registrar('prueba');

        try {
            $registro->delete();
            $this->fail('Se esperaba una excepción al borrar.');
        } catch (LogicException) {
            $this->assertModelExists($registro);
        }
    }
}

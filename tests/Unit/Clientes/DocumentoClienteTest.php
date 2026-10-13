<?php

namespace Tests\Unit\Clientes;

use App\Rules\DocumentoClienteValido;
use App\Services\DocumentoCliente;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DocumentoClienteTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function validos(): array
    {
        return [
            'DNI de 8 dígitos con puntos' => ['30.123.456'],
            'DNI de 7 dígitos' => ['1234567'],
            'CUIT de persona con guiones' => ['20-30123456-3'],
            'CUIT de empresa sin guiones' => ['30712345671'],
            'con espacios' => [' 30 123 456 '],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidos(): array
    {
        return [
            'muy corto' => ['123456'],
            '9 dígitos' => ['301234567'],
            '10 dígitos' => ['3012345678'],
            'CUIT con dígito verificador incorrecto' => ['20-30123456-0'],
            'CUIT con prefijo inválido' => ['11-30123456-3'],
            'texto' => ['abc'],
            'vacío' => [''],
        ];
    }

    #[DataProvider('validos')]
    public function test_valid_documents_are_accepted(string $documento): void
    {
        $this->assertTrue(DocumentoCliente::esValido($documento));
        $this->assertSame([], $this->errores($documento));
    }

    #[DataProvider('invalidos')]
    public function test_invalid_documents_are_rejected(string $documento): void
    {
        $this->assertFalse(DocumentoCliente::esValido($documento));
        $this->assertSame(['El documento debe ser un DNI de 7 u 8 dígitos o un CUIT válido.'], $this->errores($documento));
    }

    public function test_documents_are_normalized_and_formatted(): void
    {
        $this->assertSame('30123456', DocumentoCliente::normalizar('30.123.456'));
        $this->assertSame('30.123.456', DocumentoCliente::formatear('30123456'));
        $this->assertSame('1.234.567', DocumentoCliente::formatear('1234567'));
        $this->assertSame('20-30123456-3', DocumentoCliente::formatear('20301234563'));
        $this->assertSame('', DocumentoCliente::formatear(null));
    }

    public function test_only_valid_eleven_digit_documents_are_cuits(): void
    {
        $this->assertTrue(DocumentoCliente::esCuit('20301234563'));
        $this->assertFalse(DocumentoCliente::esCuit('30123456'));
        $this->assertFalse(DocumentoCliente::esCuit('20301234560'));
    }

    /**
     * @return list<string>
     */
    private function errores(string $documento): array
    {
        $errores = [];

        (new DocumentoClienteValido)->validate('dni_cuit', $documento, function (string $mensaje) use (&$errores) {
            $errores[] = $mensaje;
        });

        return $errores;
    }
}

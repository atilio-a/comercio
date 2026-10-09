<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Imágenes reales y mínimas para tests. UploadedFile::fake()->image() necesita GD,
 * que no está instalado en este entorno.
 */
class ImagenesDePrueba
{
    /**
     * JPEG válido de 1×1 px (escala de grises).
     */
    private const JPEG_1X1 = '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q==';

    /**
     * PNG de color sólido con las dimensiones pedidas (comprimido, pesa pocos KB).
     */
    public static function png(int $ancho, int $alto): string
    {
        $fila = "\0".str_repeat("\xC8\x96\x64", $ancho);
        $datos = gzcompress(str_repeat($fila, $alto), 9);

        return "\x89PNG\r\n\x1A\n"
            .self::fragmentoPng('IHDR', pack('NNCCCCC', $ancho, $alto, 8, 2, 0, 0, 0))
            .self::fragmentoPng('IDAT', $datos)
            .self::fragmentoPng('IEND', '');
    }

    /**
     * JPEG cuyo encabezado declara las dimensiones pedidas (lo que leen getimagesize y la
     * validación `dimensions`).
     */
    public static function jpeg(int $ancho = 1, int $alto = 1): string
    {
        $bytes = base64_decode(self::JPEG_1X1);
        $sof = strpos($bytes, "\xFF\xC0");

        if ($sof === false) {
            throw new RuntimeException('JPEG de prueba sin marcador SOF0.');
        }

        // SOF0: marcador (2) + longitud (2) + precisión (1) + alto (2) + ancho (2).
        return substr_replace($bytes, pack('nn', $alto, $ancho), $sof + 5, 4);
    }

    public static function archivoPng(string $nombre = 'foto.png', int $ancho = 1600, int $alto = 1200): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, self::png($ancho, $alto));
    }

    public static function archivoJpeg(string $nombre = 'foto.jpg', int $ancho = 1600, int $alto = 1200): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, self::jpeg($ancho, $alto));
    }

    /**
     * Un PDF con extensión .jpg, para probar que se valida el contenido y no la extensión.
     */
    public static function pdfDisfrazado(string $nombre = 'foto.jpg'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
    }

    private static function fragmentoPng(string $tipo, string $datos): string
    {
        return pack('N', strlen($datos)).$tipo.$datos.pack('N', crc32($tipo.$datos));
    }
}

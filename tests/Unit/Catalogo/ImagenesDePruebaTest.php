<?php

namespace Tests\Unit\Catalogo;

use finfo;
use PHPUnit\Framework\TestCase;
use Tests\Support\ImagenesDePrueba;

class ImagenesDePruebaTest extends TestCase
{
    public function test_png_has_the_requested_dimensions_and_type(): void
    {
        $png = ImagenesDePrueba::png(1600, 1200);

        $info = getimagesizefromstring($png);
        $this->assertSame([1600, 1200], [$info[0], $info[1]]);
        $this->assertSame('image/png', (new finfo(FILEINFO_MIME_TYPE))->buffer($png));
        $this->assertLessThan(50_000, strlen($png));
    }

    public function test_jpeg_has_the_requested_dimensions_and_type(): void
    {
        $jpeg = ImagenesDePrueba::jpeg(400, 300);

        $info = getimagesizefromstring($jpeg);
        $this->assertSame([400, 300], [$info[0], $info[1]]);
        $this->assertSame('image/jpeg', (new finfo(FILEINFO_MIME_TYPE))->buffer($jpeg));
    }

    public function test_disguised_pdf_is_detected_as_pdf(): void
    {
        $archivo = ImagenesDePrueba::pdfDisfrazado();

        $this->assertSame('application/pdf', (new finfo(FILEINFO_MIME_TYPE))->file($archivo->getPathname()));
    }
}

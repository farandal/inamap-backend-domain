<?php

namespace Domain\App\Services\Inamap\Qr;

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Genera códigos QR (ubicaciones y sesiones de ruta) en SVG o PNG.
 */
class QrService
{
    public const FORMATS = ['svg', 'png'];

    /** @return array{content: string, mime: string} */
    public function render(string $data, string $format = 'svg', int $size = 320): array
    {
        $qr = new QrCode(data: $data, errorCorrectionLevel: ErrorCorrectionLevel::Medium, size: $size, margin: 12);

        if ($format === 'png') {
            return ['content' => (new PngWriter())->write($qr)->getString(), 'mime' => 'image/png'];
        }

        return ['content' => (new SvgWriter())->write($qr)->getString(), 'mime' => 'image/svg+xml'];
    }

    public function svg(string $data, int $size = 320): string
    {
        return $this->render($data, 'svg', $size)['content'];
    }
}

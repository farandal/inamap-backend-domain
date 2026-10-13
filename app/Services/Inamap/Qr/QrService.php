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
    /**
     * @param  bool  $compact  lowest error correction: fewer modules for the same
     *                         text, which is what a long link needs to stay
     *                         scannable at a small size on a screen (a printed or
     *                         scuffed code would want the default).
     */
    public function render(string $data, string $format = 'svg', int $size = 320, bool $compact = false): array
    {
        $qr = new QrCode(
            data: $data,
            errorCorrectionLevel: $compact ? ErrorCorrectionLevel::Low : ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 12,
        );

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

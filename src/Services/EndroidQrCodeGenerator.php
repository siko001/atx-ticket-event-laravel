<?php

namespace AtxDigital\Ticketing\Services;

use AtxDigital\Ticketing\Contracts\QrCodeGeneratorContract;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

class EndroidQrCodeGenerator implements QrCodeGeneratorContract
{
    public function generate(string $data, ?int $size = null): string
    {
        $size ??= (int) config('ticketing.qr.size', 300);

        $builder = new Builder(
            writer: new PngWriter,
            data: $data,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 10,
        );

        return $builder->build()->getString();
    }
}

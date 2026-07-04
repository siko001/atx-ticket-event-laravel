<?php

namespace AtxDigital\Ticketing\Contracts;

interface QrCodeGeneratorContract
{
    /**
     * Render the given payload as a QR code and return binary PNG data.
     */
    public function generate(string $data, ?int $size = null): string;
}

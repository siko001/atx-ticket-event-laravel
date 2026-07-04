<?php

namespace AtxDigital\Ticketing\Tests\Support;

use AtxDigital\Ticketing\Contracts\QrCodeGeneratorContract;

class FakeQrCodeGenerator implements QrCodeGeneratorContract
{
    public function generate(string $data, ?int $size = null): string
    {
        return 'fake-png:'.$data;
    }
}

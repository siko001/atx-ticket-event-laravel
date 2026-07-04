<?php

namespace AtxDigital\Ticketing\Tests\Support;

use AtxDigital\Ticketing\Contracts\PdfGeneratorContract;

class FakePdfGenerator implements PdfGeneratorContract
{
    public function generate(string $view, array $data = []): string
    {
        return '%PDF-1.4 fake ticket for '.($data['attendee']->name ?? 'unknown');
    }
}

<?php

namespace AtxDigital\Ticketing\Contracts;

interface PdfGeneratorContract
{
    /**
     * Render a Blade view to a PDF and return the binary PDF content.
     *
     * @param  array<string, mixed>  $data
     */
    public function generate(string $view, array $data = []): string;
}

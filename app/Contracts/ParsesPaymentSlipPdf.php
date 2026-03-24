<?php

namespace App\Contracts;

use App\Data\SlipResult;

interface ParsesPaymentSlipPdf
{
    /**
     * @return SlipResult[]
     */
    public function __invoke(string $text): array;
}

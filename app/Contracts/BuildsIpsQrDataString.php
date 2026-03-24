<?php

namespace App\Contracts;

use App\Data\ParsedSlipData;

interface BuildsIpsQrDataString
{
    public function __invoke(ParsedSlipData $slip): ?string;
}

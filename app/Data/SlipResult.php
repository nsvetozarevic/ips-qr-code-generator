<?php

namespace App\Data;

readonly class SlipResult
{
    public function __construct(
        public ParsedSlipData $parsed,
        public ?string $ipsQrData,
    ) {}
}

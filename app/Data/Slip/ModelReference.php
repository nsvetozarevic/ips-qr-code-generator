<?php

namespace App\Data\Slip;

readonly class ModelReference
{
    public function __construct(
        public ?string $model = null,
        public ?string $referenceNumber = null,
    ) {}
}

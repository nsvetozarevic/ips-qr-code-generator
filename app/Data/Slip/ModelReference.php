<?php

namespace App\Data\Slip;

class ModelReference
{
    public function __construct(
        public readonly ?string $model = null,
        public readonly ?string $referenceNumber = null,
    ) {}
}

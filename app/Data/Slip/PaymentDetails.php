<?php

namespace App\Data\Slip;

class PaymentDetails
{
    public function __construct(
        public readonly ?string $paymentCode = null,
        public readonly ?string $currency = null,
        public readonly ?string $amount = null,
    ) {}
}

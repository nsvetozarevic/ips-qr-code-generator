<?php

namespace App\Data\Slip;

readonly class PaymentDetails
{
    public function __construct(
        public ?string $paymentCode = null,
        public ?string $currency = null,
        public ?string $amount = null,
    ) {}
}

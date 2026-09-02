<?php

namespace App\Data;

readonly class ParsedSlipData
{
    public function __construct(
        public ?string $payer = null,
        public ?string $purpose = null,
        public ?string $payee = null,
        public ?string $amount = null,
        public ?string $currency = null,
        public ?string $payerAccount = null,
        public ?string $recipientAccount = null,
        public ?string $paymentCode = null,
        public ?string $debtorModel = null,
        public ?string $debtorReferenceNumber = null,
        public ?string $creditorModel = null,
        public ?string $creditorReferenceNumber = null,
    ) {}
}

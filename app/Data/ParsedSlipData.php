<?php

namespace App\Data;

class ParsedSlipData
{
    public function __construct(
        public readonly ?string $payer = null,
        public readonly ?string $purpose = null,
        public readonly ?string $payee = null,
        public readonly ?string $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $payerAccount = null,
        public readonly ?string $recipientAccount = null,
        public readonly ?string $paymentCode = null,
        public readonly ?string $debtorModel = null,
        public readonly ?string $debtorReferenceNumber = null,
        public readonly ?string $creditorModel = null,
        public readonly ?string $creditorReferenceNumber = null,
    ) {}
}

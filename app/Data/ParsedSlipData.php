<?php

namespace App\Data;

readonly class ParsedSlipData
{
    public ?string $payer;

    public ?string $purpose;

    public ?string $payee;

    public ?string $amount;

    public ?string $currency;

    public ?string $payerAccount;

    public ?string $recipientAccount;

    public ?string $paymentCode;

    public ?string $debtorModel;

    public ?string $debtorReferenceNumber;

    public ?string $creditorModel;

    public ?string $creditorReferenceNumber;

    public function __construct(
        ?string $payer = null,
        ?string $purpose = null,
        ?string $payee = null,
        ?string $amount = null,
        ?string $currency = null,
        ?string $payerAccount = null,
        ?string $recipientAccount = null,
        ?string $paymentCode = null,
        ?string $debtorModel = null,
        ?string $debtorReferenceNumber = null,
        ?string $creditorModel = null,
        ?string $creditorReferenceNumber = null,
    ) {
        $this->payer = self::replaceEmDash($payer);
        $this->purpose = self::replaceEmDash($purpose);
        $this->payee = self::replaceEmDash($payee);
        $this->amount = self::replaceEmDash($amount);
        $this->currency = self::replaceEmDash($currency);
        $this->payerAccount = self::replaceEmDash($payerAccount);
        $this->recipientAccount = self::replaceEmDash($recipientAccount);
        $this->paymentCode = self::replaceEmDash($paymentCode);
        $this->debtorModel = self::replaceEmDash($debtorModel);
        $this->debtorReferenceNumber = self::replaceEmDash($debtorReferenceNumber);
        $this->creditorModel = self::replaceEmDash($creditorModel);
        $this->creditorReferenceNumber = self::replaceEmDash($creditorReferenceNumber);
    }

    /**
     * IPS text allows a hyphen and rejects an em dash.
     */
    private static function replaceEmDash(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return str_replace('—', '-', $value);
    }
}

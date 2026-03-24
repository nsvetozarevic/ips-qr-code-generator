<?php

namespace App\Actions;

use App\Contracts\BuildsIpsQrDataString;
use App\Data\ParsedSlipData;
use App\Support\SerbianBankAccount;

class BuildIpsQrDataStringAction implements BuildsIpsQrDataString
{
    private const TAG_ORDER = ['K', 'V', 'C', 'R', 'N', 'I', 'SF', 'S', 'RO'];

    public function __invoke(ParsedSlipData $slip): ?string
    {
        if (
            ! $slip->payee
            || ! $slip->recipientAccount
            || ! $slip->paymentCode
            || ! $slip->amount
            || ! $slip->currency
        ) {
            return null;
        }

        $normalizedAccount = SerbianBankAccount::normalize($slip->recipientAccount);

        if (! SerbianBankAccount::isValidChecksum($normalizedAccount)) {
            return null;
        }

        $amountField = $this->formatAmount($slip->amount, $slip->currency);

        if ($amountField === null) {
            return null;
        }

        $tags = [
            'K' => 'PR',
            'V' => '01',
            'C' => '1',
            'R' => $normalizedAccount,
            'N' => mb_substr($slip->payee, 0, 70),
            'I' => $amountField,
            'SF' => $slip->paymentCode,
        ];

        if ($slip->purpose !== null && trim($slip->purpose) !== '') {
            $tags['S'] = mb_substr(trim($slip->purpose), 0, 35);
        }

        $ro = $this->buildPozivNaBroj($slip);

        if ($ro !== null) {
            $tags['RO'] = mb_substr($ro, 0, 35);
        }

        $parts = [];

        foreach (self::TAG_ORDER as $key) {
            if (isset($tags[$key])) {
                $parts[] = sprintf('%s:%s', $key, str_replace('|', '-', $tags[$key]));
            }
        }

        return implode('|', $parts);
    }

    private function formatAmount(string $amount, string $currency): ?string
    {
        $normalized = str_replace(',', '.', $amount);

        if (! is_numeric($normalized)) {
            return null;
        }

        $value = (float) $normalized;
        $whole = (int) $value;
        $cents = (int) round(($value - $whole) * 100);

        return $cents === 0
            ? sprintf('%s%d,', mb_strtoupper($currency), $whole)
            : sprintf('%s%d,%02d', mb_strtoupper($currency), $whole, $cents);
    }

    private function buildPozivNaBroj(ParsedSlipData $slip): ?string
    {
        $creditorRef = $slip->creditorReferenceNumber !== null ? trim($slip->creditorReferenceNumber) : null;
        $debtorRef = $slip->debtorReferenceNumber !== null ? trim($slip->debtorReferenceNumber) : null;

        if ($creditorRef !== null && $creditorRef !== '') {
            $model = $slip->creditorModel !== null ? trim($slip->creditorModel) : '';

            return $model.(string) preg_replace('/\s+/', '', $creditorRef);
        }

        if ($debtorRef !== null && $debtorRef !== '') {
            $model = $slip->debtorModel !== null ? trim($slip->debtorModel) : '';

            return $model.(string) preg_replace('/\s+/', '', $debtorRef);
        }

        return null;
    }
}

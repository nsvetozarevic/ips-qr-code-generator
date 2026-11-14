<?php

namespace App\Actions;

use App\Contracts\BuildsIpsQrDataString;
use App\Contracts\ParsesPaymentSlipPdf;
use App\Data\ParsedSlipData;
use App\Data\Slip\ModelReference;
use App\Data\Slip\PaymentDetails;
use App\Data\SlipResult;

class ParsePaymentSlipPdfAction implements ParsesPaymentSlipPdf
{
    public function __construct(
        private readonly BuildsIpsQrDataString $buildIpsQrDataString,
    ) {}

    private const SLIP_TITLE_PATTERN = '/налог за пренос/ui';

    private const COLUMN_BOUNDARY = 55;

    private const LEFT_LABEL_PATTERNS = [
        'payer' => '/^платилац$/ui',
        'purpose' => '/^сврха плаћања/ui',
        'payee' => '/^прималац$/ui',
    ];

    private const RIGHT_LABEL_PATTERNS = [
        'paymentDetails' => '/^шифра\s*плаћања/ui',
        'payerAccount' => '/^рачун платиоца/ui',
        'debtorDetails' => '/^модел и позив на број\s*\(задужење\)/ui',
        'recipientAccount' => '/^рачун примаоца/ui',
        'creditorDetails' => '/^модел и позив на број\s*\(одобрења\)/ui',
    ];

    public function __invoke(string $text): array
    {
        $text = str_replace("\f", "\n", $text);
        $results = [];

        foreach ($this->splitIntoSlipBlocks($text) as $block) {
            $parsed = $this->parseSlipBlock($block);

            if ($this->isEmptySlip($parsed)) {
                continue;
            }

            $results[] = new SlipResult(
                parsed: $parsed,
                ipsQrData: ($this->buildIpsQrDataString)($parsed),
            );
        }

        return $results;
    }

    private function splitIntoSlipBlocks(string $text): array
    {
        $blocks = preg_split(self::SLIP_TITLE_PATTERN, $text);

        if ($blocks === false) {
            return [];
        }

        $trimmedBlocks = [];

        foreach ($blocks as $block) {
            $block = trim($block);

            if ($block === '') {
                continue;
            }

            $trimmedBlocks[] = $block;
        }

        if ($trimmedBlocks !== []) {
            return $trimmedBlocks;
        }

        $trimmedText = trim($text);

        return $trimmedText !== '' ? [$trimmedText] : [];
    }

    private function parseSlipBlock(string $block): ParsedSlipData
    {
        $streams = $this->splitIntoColumnStreams($block);

        if ($this->isRightColumnEmpty($streams['right'])) {
            return $this->parseSlipBlockSingleColumn($block);
        }

        $rightLines = $this->mergeRightColumnFragments($streams['right']);
        $leftFields = $this->extractFields($streams['left'], self::LEFT_LABEL_PATTERNS);
        $rightFields = $this->extractFields($rightLines, self::RIGHT_LABEL_PATTERNS);
        $fields = $this->normalizeDomesticAccountFields(
            $this->refineDomesticAccountFieldsFromRawLines(
                $block,
                array_merge($leftFields, $rightFields),
            ),
        );

        $payment = $this->parsePaymentDetails($fields['paymentDetails'] ?? null);
        $debtor = $this->parseModelAndReference($fields['debtorDetails'] ?? null);
        $creditor = $this->parseModelAndReference($fields['creditorDetails'] ?? null);

        return new ParsedSlipData(
            payer: $fields['payer'] ?? null,
            purpose: $fields['purpose'] ?? null,
            payee: $fields['payee'] ?? null,
            amount: $payment->amount,
            currency: $payment->currency,
            payerAccount: $fields['payerAccount'] ?? null,
            recipientAccount: $fields['recipientAccount'] ?? null,
            paymentCode: $payment->paymentCode,
            debtorModel: $debtor->model,
            debtorReferenceNumber: $debtor->referenceNumber,
            creditorModel: $creditor->model,
            creditorReferenceNumber: $creditor->referenceNumber,
        );
    }

    private function parseSlipBlockSingleColumn(string $block): ParsedSlipData
    {
        $lines = $this->toTrimmedLines($block);
        $fields = $this->normalizeDomesticAccountFields(
            $this->refineDomesticAccountFieldsFromRawLines(
                $block,
                $this->extractFields($lines, $this->allLabelPatterns()),
            ),
        );

        $payment = $this->parsePaymentDetails($fields['paymentDetails'] ?? null);
        $debtor = $this->parseModelAndReference($fields['debtorDetails'] ?? null);
        $creditor = $this->parseModelAndReference($fields['creditorDetails'] ?? null);

        return new ParsedSlipData(
            payer: $fields['payer'] ?? null,
            purpose: $fields['purpose'] ?? null,
            payee: $fields['payee'] ?? null,
            amount: $payment->amount,
            currency: $payment->currency,
            payerAccount: $fields['payerAccount'] ?? null,
            recipientAccount: $fields['recipientAccount'] ?? null,
            paymentCode: $payment->paymentCode,
            debtorModel: $debtor->model,
            debtorReferenceNumber: $debtor->referenceNumber,
            creditorModel: $creditor->model,
            creditorReferenceNumber: $creditor->referenceNumber,
        );
    }

    /**
     * @return array<string, string>
     */
    private function allLabelPatterns(): array
    {
        return array_merge(self::LEFT_LABEL_PATTERNS, self::RIGHT_LABEL_PATTERNS);
    }

    /**
     * @return array<int, string>
     */
    private function toTrimmedLines(string $block): array
    {
        $lines = explode("\n", $block);
        $trimmed = [];

        foreach ($lines as $line) {
            $trimmed[] = trim($line);
        }

        return $trimmed;
    }

    /**
     * @param  array<int, string>  $rightLines
     */
    private function isRightColumnEmpty(array $rightLines): bool
    {
        foreach ($rightLines as $line) {
            if (trim($line) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{left: array<int, string>, right: array<int, string>}
     */
    private function splitIntoColumnStreams(string $block): array
    {
        $leftLines = [];
        $rightLines = [];

        foreach (explode("\n", $block) as $line) {
            // A centered footer such as "образац бр.3" straddles the column cut.
            // Slicing it first leaves a stray letter that is later read as a field.
            if ($this->isIgnoredFooterLine(trim($line))) {
                $leftLines[] = '';
                $rightLines[] = '';

                continue;
            }

            [$left, $right] = $this->splitByColumn($line, self::COLUMN_BOUNDARY);
            $leftLines[] = $left;
            $rightLines[] = $right;
        }

        return [
            'left' => $leftLines,
            'right' => $rightLines,
        ];
    }

    private function splitByColumn(string $line, int $boundary): array
    {
        $length = mb_strlen($line);

        if ($length <= $boundary) {
            return [trim($line), ''];
        }

        $left = mb_substr($line, 0, $boundary);
        $right = mb_substr($line, $boundary);

        return [trim($left), trim($right)];
    }

    /**
     * @param  array<int, string>  $rightLines
     * @return array<int, string>
     */
    private function mergeRightColumnFragments(array $rightLines): array
    {
        $merged = [];
        $count = count($rightLines);

        for ($i = 0; $i < $count; $i++) {
            $line = $rightLines[$i];

            if ($line === '') {
                $merged[] = '';

                continue;
            }

            if (preg_match('/^шифра$/u', $line) === 1 && $i + 1 < $count) {
                $next = $rightLines[$i + 1];

                if ($next !== '' && preg_match('/^плаћања/ui', $next) === 1) {
                    $merged[] = trim($line).' '.$next;
                    $i++;

                    continue;
                }
            }

            $merged[] = $line;
        }

        return $merged;
    }

    /**
     * @param  array<int, string>  $lines
     * @param  array<string, string>  $labelPatterns
     * @return array<string, string>
     */
    private function extractFields(array $lines, array $labelPatterns): array
    {
        $fields = [];
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $line = $lines[$i];

            foreach ($labelPatterns as $field => $pattern) {
                if (! preg_match($pattern, $line)) {
                    continue;
                }

                $value = $this->nextNonEmptyLine($lines, $i + 1, $labelPatterns);

                if ($value !== null) {
                    $fields[$field] = $value;
                }
            }
        }

        return $fields;
    }

    /**
     * @param  array<int, string>  $lines
     * @param  array<string, string>  $labelPatterns
     */
    private function nextNonEmptyLine(array $lines, int $from, array $labelPatterns): ?string
    {
        $count = count($lines);

        for ($i = $from; $i < $count; $i++) {
            $line = trim($lines[$i]);

            if ($line === '') {
                continue;
            }

            if ($this->isLabel($line, $labelPatterns)) {
                return null;
            }

            if ($this->isIgnoredFooterLine($line)) {
                continue;
            }

            return $line;
        }

        return null;
    }

    /**
     * @param  array<string, string>  $labelPatterns
     */
    private function isLabel(string $line, array $labelPatterns): bool
    {
        foreach ($labelPatterns as $pattern) {
            if (preg_match($pattern, $line) === 1) {
                return true;
            }
        }

        return false;
    }

    private function isIgnoredFooterLine(string $line): bool
    {
        return preg_match(
            '/^(печат|датум извршења|место и датум|хитно|образац|разац\s*бр|бр\.\s*\d+)/ui',
            $line,
        ) === 1
            || preg_match('/^разац/ui', $line) === 1;
    }

    private function parsePaymentDetails(?string $section): PaymentDetails
    {
        if ($section === null) {
            return new PaymentDetails;
        }

        $paymentCode = null;
        $currency = null;
        $amount = null;

        if (preg_match('/\b\d{3}\b/u', $section, $match) === 1) {
            $paymentCode = $match[0];
        }

        if (preg_match('/\b[A-Za-z]{3}\b/u', $section, $match) === 1) {
            $currency = mb_strtoupper($match[0]);
        }

        if (preg_match('/=?([\d.,]+)\s*$/u', $section, $match) === 1) {
            $amount = $match[1];
        }

        return new PaymentDetails(
            paymentCode: $paymentCode,
            currency: $currency,
            amount: $amount,
        );
    }

    private function parseModelAndReference(?string $section): ModelReference
    {
        if ($section === null) {
            return new ModelReference;
        }

        $section = trim($section);

        if ($section === '') {
            return new ModelReference;
        }

        if ($this->isIgnoredFooterLine($section) || $this->isBoilerplateNotModelReference($section)) {
            return new ModelReference;
        }

        if (preg_match('/^(?<model>\d{2})\s+(?<reference>.+)$/u', $section, $match) === 1) {
            return new ModelReference(model: $match['model'], referenceNumber: $match['reference']);
        }

        if (preg_match('/^\d{2}$/u', $section) === 1) {
            return new ModelReference(model: $section);
        }

        return new ModelReference(referenceNumber: $section);
    }

    private function isBoilerplateNotModelReference(string $section): bool
    {
        return preg_match(
            '/разац|образац|печат|потпис|датум извршења|место и датум|хитно|образац\s*бр/ui',
            $section,
        ) === 1;
    }

    /**
     * Reads the full next line after account labels so values are not lost when the column split
     * puts the label on the left stream and the account on the right stream of the following line.
     *
     * @param  array<string, string>  $fields
     * @return array<string, string>
     */
    private function refineDomesticAccountFieldsFromRawLines(string $block, array $fields): array
    {
        $rawLines = explode("\n", $block);

        $fields = $this->refineAccountFieldFromRawLines(
            $rawLines,
            $fields,
            '/^рачун\s+платиоца/ui',
            'payerAccount',
            false,
        );

        return $this->refineAccountFieldFromRawLines(
            $rawLines,
            $fields,
            '/^рачун\s+примаоца/ui',
            'recipientAccount',
            true,
        );
    }

    /**
     * @param  array<int, string>  $rawLines
     * @param  array<string, string>  $fields
     * @return array<string, string>
     */
    private function refineAccountFieldFromRawLines(
        array $rawLines,
        array $fields,
        string $labelPattern,
        string $fieldKey,
        bool $appendAddressFragmentToPayee,
    ): array {
        $count = count($rawLines);

        for ($i = 0; $i < $count; $i++) {
            $trimmed = trim($rawLines[$i]);

            if (preg_match($labelPattern, $trimmed) !== 1) {
                continue;
            }

            $valueLine = $this->nextRawValueLineAfterLabel($rawLines, $i + 1);

            if ($valueLine === null) {
                break;
            }

            $account = $this->extractDomesticAccountNumber($valueLine);

            if ($account === null) {
                break;
            }

            $fields[$fieldKey] = $account;

            if ($appendAddressFragmentToPayee) {
                $beforeAccount = $this->textBeforeLastDomesticAccount($valueLine, $account);

                if ($beforeAccount !== null && $beforeAccount !== '') {
                    $payee = $fields['payee'] ?? '';
                    $fields['payee'] = trim($payee.' '.$beforeAccount);
                }
            }

            break;
        }

        return $fields;
    }

    /**
     * @param  array<int, string>  $rawLines
     */
    private function nextRawValueLineAfterLabel(array $rawLines, int $from): ?string
    {
        $count = count($rawLines);
        $allPatterns = $this->allLabelPatterns();

        for ($i = $from; $i < $count; $i++) {
            $line = trim($rawLines[$i]);

            if ($line === '') {
                continue;
            }

            if ($this->isLabel($line, $allPatterns)) {
                return null;
            }

            if ($this->isIgnoredFooterLine($line)) {
                continue;
            }

            return $line;
        }

        return null;
    }

    /**
     * @param  array<string, string>  $fields
     * @return array<string, string>
     */
    private function normalizeDomesticAccountFields(array $fields): array
    {
        $payerAccount = $fields['payerAccount'] ?? null;

        if ($payerAccount !== null) {
            $extracted = $this->extractDomesticAccountNumber($payerAccount);

            if ($extracted !== null) {
                $fields['payerAccount'] = $extracted;
            }
        }

        $recipientAccount = $fields['recipientAccount'] ?? null;

        if ($recipientAccount !== null) {
            $extracted = $this->extractDomesticAccountNumber($recipientAccount);

            if ($extracted !== null) {
                $beforeAccount = $this->textBeforeLastDomesticAccount($recipientAccount, $extracted);
                $fields['recipientAccount'] = $extracted;

                if ($beforeAccount !== null && $beforeAccount !== '') {
                    $payee = $fields['payee'] ?? '';
                    $fields['payee'] = trim($payee.' '.$beforeAccount);
                }
            }
        }

        return $fields;
    }

    private function extractDomesticAccountNumber(string $value): ?string
    {
        if (preg_match_all('/\d{3}-\d+-\d{2}/u', $value, $matches) === 0) {
            return null;
        }

        $accounts = $matches[0];

        return $accounts[array_key_last($accounts)];
    }

    private function textBeforeLastDomesticAccount(string $value, string $account): ?string
    {
        $lastPos = mb_strrpos($value, $account);

        if ($lastPos === false) {
            return null;
        }

        $before = trim(mb_substr($value, 0, $lastPos));

        return $before === '' ? null : $before;
    }

    private function isEmptySlip(ParsedSlipData $slip): bool
    {
        foreach (get_object_vars($slip) as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }
}

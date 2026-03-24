<?php

namespace Tests\Unit;

use App\Actions\BuildIpsQrDataStringAction;
use App\Data\ParsedSlipData;
use PHPUnit\Framework\TestCase;

class BuildIpsQrDataStringActionTest extends TestCase
{
    private BuildIpsQrDataStringAction $action;

    protected function setUp(): void
    {
        $this->action = new BuildIpsQrDataStringAction;
    }

    public function test_builds_full_ips_string(): void
    {
        $slip = $this->slip(
            payee: 'Globex doo',
            recipientAccount: '160-1234567890120-21',
            amount: '1000.00',
            currency: 'rsd',
            paymentCode: '221',
            purpose: 'po racunu',
            creditorModel: '97',
            creditorReferenceNumber: '12345678',
        );

        $result = ($this->action)($slip);

        $this->assertSame(
            'K:PR|V:01|C:1|R:160123456789012021|N:Globex doo|I:RSD1000,|SF:221|S:po racunu|RO:9712345678',
            $result,
        );
    }

    public function test_formats_amount_with_cents(): void
    {
        $slip = $this->slip(
            payee: 'Globex doo',
            recipientAccount: '160-1234567890120-21',
            amount: '1295.50',
            currency: 'rsd',
            paymentCode: '221',
        );

        $result = ($this->action)($slip);

        $this->assertNotNull($result);
        $this->assertStringContainsString('I:RSD1295,50', $result);
    }

    public function test_appends_ro_from_debtor_reference_when_no_creditor_reference(): void
    {
        $slip = $this->slip(
            payee: 'Globex doo',
            recipientAccount: '160-1234567890120-21',
            amount: '500,00',
            currency: 'RSD',
            paymentCode: '221',
            debtorModel: '97',
            debtorReferenceNumber: '99887766',
        );

        $result = ($this->action)($slip);

        $this->assertNotNull($result);
        $this->assertStringContainsString('RO:9799887766', $result);
    }

    public function test_returns_null_for_invalid_account_checksum(): void
    {
        $slip = $this->slip(
            payee: 'Globex doo',
            recipientAccount: '160-1234567890120-99',
            amount: '1000.00',
            currency: 'rsd',
            paymentCode: '221',
        );

        $this->assertNull(($this->action)($slip));
    }

    public function test_returns_null_when_required_fields_are_missing(): void
    {
        $slipWithoutPayee = $this->slip(
            recipientAccount: '160-1234567890120-21',
            amount: '1000.00',
            currency: 'rsd',
            paymentCode: '221',
        );

        $slipWithoutAccount = $this->slip(
            payee: 'Globex doo',
            amount: '1000.00',
            currency: 'rsd',
            paymentCode: '221',
        );

        $this->assertNull(($this->action)($slipWithoutPayee));
        $this->assertNull(($this->action)($slipWithoutAccount));
    }

    public function test_omits_ro_tag_when_no_reference_numbers(): void
    {
        $slip = $this->slip(
            payee: 'Globex doo',
            recipientAccount: '160-1234567890120-21',
            amount: '1000.00',
            currency: 'rsd',
            paymentCode: '221',
        );

        $result = ($this->action)($slip);

        $this->assertNotNull($result);
        $this->assertStringNotContainsString('RO:', $result);
    }

    private function slip(
        ?string $payee = null,
        ?string $recipientAccount = null,
        ?string $amount = null,
        ?string $currency = null,
        ?string $paymentCode = null,
        ?string $purpose = null,
        ?string $payer = null,
        ?string $payerAccount = null,
        ?string $creditorModel = null,
        ?string $creditorReferenceNumber = null,
        ?string $debtorModel = null,
        ?string $debtorReferenceNumber = null,
    ): ParsedSlipData {
        return new ParsedSlipData(
            payer: $payer,
            purpose: $purpose,
            payee: $payee,
            amount: $amount,
            currency: $currency,
            payerAccount: $payerAccount,
            recipientAccount: $recipientAccount,
            paymentCode: $paymentCode,
            debtorModel: $debtorModel,
            debtorReferenceNumber: $debtorReferenceNumber,
            creditorModel: $creditorModel,
            creditorReferenceNumber: $creditorReferenceNumber,
        );
    }
}

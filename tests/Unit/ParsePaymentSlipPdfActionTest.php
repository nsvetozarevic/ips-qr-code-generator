<?php

namespace Tests\Unit;

use App\Actions\ParsePaymentSlipPdfAction;
use App\Contracts\BuildsIpsQrDataString;
use App\Data\SlipResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ParsePaymentSlipPdfActionTest extends TestCase
{
    private ParsePaymentSlipPdfAction $action;

    /** @var BuildsIpsQrDataString&MockObject */
    private BuildsIpsQrDataString $buildIpsQrDataString;

    protected function setUp(): void
    {
        $this->buildIpsQrDataString = $this->createMock(BuildsIpsQrDataString::class);
        $this->action = new ParsePaymentSlipPdfAction($this->buildIpsQrDataString);
    }

    public function test_parses_single_slip_with_all_fields(): void
    {
        $text = $this->singleSlipText(
            payer: 'Acme doo',
            purpose: 'po racunu',
            payee: 'Globex doo Beograd',
            paymentCode: '221',
            currency: 'rsd',
            amount: '5000.00',
            payerAccount: '160-1234567890123-45',
            recipientAccount: '170-9876543210987-65',
        );

        $slips = ($this->action)($text);

        $this->assertCount(1, $slips);

        /** @var SlipResult $slip */
        $slip = $slips[0];

        $this->assertSame('Acme doo', $slip->parsed->payer);
        $this->assertSame('po racunu', $slip->parsed->purpose);
        $this->assertSame('Globex doo Beograd', $slip->parsed->payee);
        $this->assertSame('221', $slip->parsed->paymentCode);
        $this->assertSame('RSD', $slip->parsed->currency);
        $this->assertSame('5000.00', $slip->parsed->amount);
        $this->assertSame('160-1234567890123-45', $slip->parsed->payerAccount);
        $this->assertSame('170-9876543210987-65', $slip->parsed->recipientAccount);
    }

    public function test_parses_multiple_slips_from_text(): void
    {
        $text = sprintf(
            "%s\n\nНАЛОГ ЗА ПРЕНОС\n\n%s",
            $this->singleSlipText(payer: 'Payer One', payee: 'Payee One'),
            $this->singleSlipText(payer: 'Payer Two', payee: 'Payee Two'),
        );

        $slips = ($this->action)($text);

        $this->assertCount(2, $slips);
        $this->assertSame('Payer One', $slips[0]->parsed->payer);
        $this->assertSame('Payer Two', $slips[1]->parsed->payer);
    }

    public function test_skips_empty_slip_blocks(): void
    {
        $emptyBlock = "платилац\n\nсврха плаћања\n\nпрималац\n\nшифра плаћања\n\nрачун платиоца\n\nмодел и позив на број (задужење)\n\nрачун примаоца\n\nмодел и позив на број (одобрења)";

        $text = sprintf(
            "%s\n\nНАЛОГ ЗА ПРЕНОС\n\n%s",
            $this->singleSlipText(payer: 'Acme doo', payee: 'Globex doo Beograd'),
            $emptyBlock,
        );

        $slips = ($this->action)($text);

        $this->assertCount(1, $slips);
        $this->assertSame('Acme doo', $slips[0]->parsed->payer);
    }

    public function test_returns_partial_data_for_incomplete_slip(): void
    {
        $text = "НАЛОГ ЗА ПРЕНОС\n\nплатилац\nAcme doo\n\nсврха плаћања\npo racunu\n\nпрималац\n\nшифра плаћања\n\nрачун платиоца\n\nмодел и позив на број (задужење)\n\nрачун примаоца\n\nмодел и позив на број (одобрења)";

        $slips = ($this->action)($text);

        $this->assertCount(1, $slips);
        $this->assertSame('Acme doo', $slips[0]->parsed->payer);
        $this->assertSame('po racunu', $slips[0]->parsed->purpose);
        $this->assertNull($slips[0]->parsed->payee);
        $this->assertNull($slips[0]->parsed->recipientAccount);
    }

    public function test_parses_debtor_model_and_reference_number(): void
    {
        $text = "НАЛОГ ЗА ПРЕНОС\n\nплатилац\nPayer\n\nсврха плаћања\nPurpose\n\nпрималац\nPayee\n\nшифра плаћања\n221 rsd 5000.00\n\nрачун платиоца\n160-111\n\nмодел и позив на број (задужење)\n97 12345678\n\nрачун примаоца\n170-222\n\nмодел и позив на број (одобрења)\n00 98765432";

        $slips = ($this->action)($text);

        $this->assertCount(1, $slips);
        $this->assertSame('97', $slips[0]->parsed->debtorModel);
        $this->assertSame('12345678', $slips[0]->parsed->debtorReferenceNumber);
        $this->assertSame('00', $slips[0]->parsed->creditorModel);
        $this->assertSame('98765432', $slips[0]->parsed->creditorReferenceNumber);
    }

    public function test_extracts_recipient_account_when_same_line_has_address_fragment(): void
    {
        $text = sprintf(
            "%s\n\n%s\n\n%s\n\n%s\n\n%s\n\n%s\n\n%s\n\n%s\n\n%s",
            'НАЛОГ ЗА ПРЕНОС',
            'платилац',
            'INVITIOUS DOO',
            'сврха плаћања',
            'PID- 2 -2026',
            'прималац',
            'Ministarstvo finansija RS, P.uprava-Beograd, Save Maškovića',
            'рачун примаоца',
            'ića 3-5                                   840-4848-37',
        );

        $slips = ($this->action)($text);

        $this->assertCount(1, $slips);
        $this->assertSame('840-4848-37', $slips[0]->parsed->recipientAccount);
        $this->assertStringContainsString('Ministarstvo', $slips[0]->parsed->payee);
        $this->assertStringContainsString('ića 3-5', $slips[0]->parsed->payee);
    }

    public function test_returns_empty_array_for_blank_text(): void
    {
        $slips = ($this->action)('');

        $this->assertSame([], $slips);
    }

    public function test_calls_ips_action_for_each_parsed_slip(): void
    {
        $this->buildIpsQrDataString
            ->expects($this->once())
            ->method('__invoke')
            ->willReturn('K:PR|V:01|C:1|R:123456789012345678');

        $slips = ($this->action)($this->singleSlipText(payer: 'Payer', payee: 'Payee'));

        $this->assertCount(1, $slips);
        $this->assertSame('K:PR|V:01|C:1|R:123456789012345678', $slips[0]->ipsQrData);
    }

    private function singleSlipText(
        string $payer = '',
        string $purpose = '',
        string $payee = '',
        string $paymentCode = '221',
        string $currency = 'rsd',
        string $amount = '1000.00',
        string $payerAccount = '',
        string $recipientAccount = '',
    ): string {
        return implode("\n\n", [
            'НАЛОГ ЗА ПРЕНОС',
            sprintf("платилац\n%s", $payer),
            sprintf("сврха плаћања\n%s", $purpose),
            sprintf("прималац\n%s", $payee),
            sprintf("шифра плаћања\n%s %s %s", $paymentCode, $currency, $amount),
            sprintf("рачун платиоца\n%s", $payerAccount),
            'модел и позив на број (задужење)',
            sprintf("рачун примаоца\n%s", $recipientAccount),
            'модел и позив на број (одобрења)',
        ]);
    }
}

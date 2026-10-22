<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Gateway\Audit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateApprovalStatus;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateSubmissionReceipt;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TemplateSubmissionReceipt::class)]
class TemplateSubmissionReceiptTest extends TestCase
{
    public function testItCarriesTheTemplateCodeTheProviderKeptAndTheRawWords(): void
    {
        $receipt = new TemplateSubmissionReceipt(
            templateCode: 'SMS_123456789',
            status: TemplateApprovalStatus::PENDING,
            providerTemplateName: 'customer_otp_login',
            providerCode: '0',
            providerMessage: 'OK',
        );

        static::assertSame('SMS_123456789', $receipt->templateCode);
        static::assertSame(TemplateApprovalStatus::PENDING, $receipt->status);
        static::assertSame('customer_otp_login', $receipt->providerTemplateName);
        static::assertSame('0', $receipt->providerCode);
        static::assertSame('OK', $receipt->providerMessage);
    }

    public function testAProviderThatSaysNothingLeavesTheRawWordsEmpty(): void
    {
        $receipt = new TemplateSubmissionReceipt(
            templateCode: 'SMS_123456789',
            status: TemplateApprovalStatus::PENDING,
        );

        static::assertNull($receipt->providerTemplateName);
        static::assertNull($receipt->providerCode);
        static::assertNull($receipt->providerMessage);
    }
}

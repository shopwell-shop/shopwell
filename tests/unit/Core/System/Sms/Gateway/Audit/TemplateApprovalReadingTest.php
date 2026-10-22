<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Gateway\Audit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateApprovalReading;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateApprovalStatus;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TemplateApprovalReading::class)]
class TemplateApprovalReadingTest extends TestCase
{
    public function testItCarriesTheMappedStateNextToTheRawWordsOfTheProvider(): void
    {
        $reading = new TemplateApprovalReading(
            status: TemplateApprovalStatus::REJECTED,
            providerStatusCode: '2',
            providerStatusMessage: 'the content contains a forbidden word',
            providerTemplateName: 'customer_otp_login',
        );

        static::assertSame(TemplateApprovalStatus::REJECTED, $reading->status);
        static::assertSame('2', $reading->providerStatusCode);
        static::assertSame('the content contains a forbidden word', $reading->providerStatusMessage);
        static::assertSame('customer_otp_login', $reading->providerTemplateName);
    }

    public function testAProviderThatNamesNoTemplateLeavesTheNameEmpty(): void
    {
        $reading = new TemplateApprovalReading(
            status: TemplateApprovalStatus::APPROVED,
            providerStatusCode: '1',
            providerStatusMessage: 'OK',
        );

        static::assertNull($reading->providerTemplateName);
    }
}

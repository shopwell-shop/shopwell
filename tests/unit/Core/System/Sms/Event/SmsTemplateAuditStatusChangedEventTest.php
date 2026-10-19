<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Event\SmsTemplateAuditStatusChangedEvent;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateApprovalStatus;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsTemplateAuditStatusChangedEvent::class)]
class SmsTemplateAuditStatusChangedEventTest extends TestCase
{
    public function testItCarriesEverythingASubscriberNeedsToFollowTheTransition(): void
    {
        $context = Context::createDefaultContext();

        $event = new SmsTemplateAuditStatusChangedEvent(
            mappingId: '0f8f7a5b1c2d4e3f8a9b0c1d2e3f4a5b',
            templateId: '1a8f7a5b1c2d4e3f8a9b0c1d2e3f4a5c',
            technicalName: 'aliyun',
            gatewayId: '2b8f7a5b1c2d4e3f8a9b0c1d2e3f4a5d',
            previousStatus: TemplateApprovalStatus::PENDING,
            status: TemplateApprovalStatus::APPROVED,
            providerTemplateCode: 'SMS_123456789',
            context: $context,
        );

        static::assertSame('0f8f7a5b1c2d4e3f8a9b0c1d2e3f4a5b', $event->getMappingId());
        static::assertSame('1a8f7a5b1c2d4e3f8a9b0c1d2e3f4a5c', $event->getTemplateId());
        static::assertSame('aliyun', $event->getTechnicalName());
        static::assertSame('2b8f7a5b1c2d4e3f8a9b0c1d2e3f4a5d', $event->getGatewayId());
        static::assertSame(TemplateApprovalStatus::PENDING, $event->getPreviousStatus());
        static::assertSame(TemplateApprovalStatus::APPROVED, $event->getStatus());
        static::assertSame('SMS_123456789', $event->getProviderTemplateCode());
        static::assertSame($context, $event->getContext());
    }

    public function testItAllowsTheProviderWideRowThatHoldsNoAccountAndNoCode(): void
    {
        // a row of the whole provider has no account, and a confirmation by hand may arrive before
        // anything was reported - the event has to allow both
        $event = new SmsTemplateAuditStatusChangedEvent(
            mappingId: '0f8f7a5b1c2d4e3f8a9b0c1d2e3f4a5b',
            templateId: '1a8f7a5b1c2d4e3f8a9b0c1d2e3f4a5c',
            technicalName: 'tencent-cloud',
            gatewayId: null,
            previousStatus: TemplateApprovalStatus::UNKNOWN,
            status: TemplateApprovalStatus::REJECTED,
            providerTemplateCode: null,
            context: Context::createDefaultContext(),
        );

        static::assertNull($event->getGatewayId());
        static::assertNull($event->getProviderTemplateCode());
        static::assertSame(TemplateApprovalStatus::UNKNOWN, $event->getPreviousStatus());
        static::assertSame(TemplateApprovalStatus::REJECTED, $event->getStatus());
    }
}

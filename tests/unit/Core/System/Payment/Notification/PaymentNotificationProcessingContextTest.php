<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationEntity;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationProcessingContext;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentNotificationProcessingContext::class)]
class PaymentNotificationProcessingContextTest extends TestCase
{
    public function testItCarriesTheRequestAndJournalState(): void
    {
        $context = new PaymentNotificationProcessingContext(
            'alipay',
            'raw-body',
            ['X-Custom' => 'value'],
            '1.2.3.4',
            'PG0001'
        );

        static::assertSame('alipay', $context->providerTechnicalName);
        static::assertSame('raw-body', $context->rawBody);
        static::assertSame(['X-Custom' => 'value'], $context->headers);
        static::assertSame('1.2.3.4', $context->remoteIp);
        static::assertSame('PG0001', $context->paymentNoHint);
        static::assertSame(PaymentNotificationEntity::RESULT_ERROR, $context->result);
        static::assertFalse($context->signatureValid);
        static::assertNull($context->orderId);
        static::assertNull($context->message);

        $context->result = PaymentNotificationEntity::RESULT_OK;
        $context->signatureValid = true;
        $context->orderId = 'order-id';
        $context->message = 'message';

        static::assertSame(PaymentNotificationEntity::RESULT_OK, $context->result);
        static::assertTrue($context->signatureValid);
        static::assertSame('order-id', $context->orderId);
        static::assertSame('message', $context->message);
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Struct\PaymentNotificationAck;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentNotificationAck::class)]
class PaymentNotificationAckTest extends TestCase
{
    public function testTheContentTypeDefaultsToPlainText(): void
    {
        $ack = new PaymentNotificationAck('success');

        static::assertSame('success', $ack->body);
        static::assertSame('text/plain; charset=utf-8', $ack->contentType);
    }

    public function testTheContentTypeCanBeGiven(): void
    {
        $ack = new PaymentNotificationAck('{"code":"SUCCESS","message":"ok"}', 'application/json');

        static::assertSame('{"code":"SUCCESS","message":"ok"}', $ack->body);
        static::assertSame('application/json', $ack->contentType);
    }

    public function testAnEmptyBodyIsAllowed(): void
    {
        $ack = new PaymentNotificationAck('');

        static::assertSame('', $ack->body);
        static::assertSame('text/plain; charset=utf-8', $ack->contentType);
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Webhook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDeliveryEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentWebhookDeliveryEntity::class)]
class PaymentWebhookDeliveryEntityTest extends TestCase
{
    public function testANewDeliveryHasNoClaim(): void
    {
        $delivery = new PaymentWebhookDeliveryEntity();

        static::assertSame(PaymentWebhookDeliveryEntity::STATUS_PENDING, $delivery->status);
        static::assertSame(0, $delivery->attemptCount);
        static::assertNull($delivery->claimToken);
        static::assertNull($delivery->claimExpiresAt);
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionCollection;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentSubscriptionEntity::class)]
class PaymentSubscriptionEntityTest extends TestCase
{
    #[DataProvider('names')]
    public function testTheNamesAreStable(string $expected, string $actual): void
    {
        static::assertSame($expected, $actual);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function names(): \Generator
    {
        yield 'entity name' => ['payment_subscription', PaymentSubscriptionEntity::ENTITY_NAME];
        yield 'api alias' => ['payment_subscription_collection', (new PaymentSubscriptionCollection())->getApiAlias()];
    }

    public function testPropertiesRoundTrip(): void
    {
        $entity = new PaymentSubscriptionEntity();
        $entity->id = Uuid::randomHex();
        $entity->subscriptionNo = 'PS1A2B3C4D';
        $entity->planId = Uuid::randomHex();
        $entity->customerId = Uuid::randomHex();
        $entity->salesChannelId = Uuid::randomHex();
        $entity->billingMode = 'channel_managed';
        $entity->provider = 'stripe';
        $entity->channel = 'stripe_card';
        $entity->providerSubscriptionId = 'sub_123';
        $entity->mandateId = null;
        $entity->statusId = Uuid::randomHex();
        $entity->nextBillingAt = new \DateTimeImmutable('2026-11-01 00:00:00');

        static::assertSame('PS1A2B3C4D', $entity->subscriptionNo);
        static::assertSame('channel_managed', $entity->billingMode);
        static::assertSame('sub_123', $entity->providerSubscriptionId);
        static::assertNull($entity->mandateId);
        static::assertSame('2026-11-01 00:00:00', $entity->nextBillingAt->format('Y-m-d H:i:s'));
    }

    public function testFailureCountDefaultsToZero(): void
    {
        $entity = new PaymentSubscriptionEntity();

        static::assertSame(0, $entity->failureCount);
    }

    public function testCollectionHoldsEntitiesById(): void
    {
        $entity = new PaymentSubscriptionEntity();
        $id = Uuid::randomHex();
        $entity->id = $id;
        $entity->subscriptionNo = 'PSZZZZZZZZ';

        $collection = new PaymentSubscriptionCollection();
        $collection->set($id, $entity);

        static::assertSame($entity, $collection->get($id));
    }
}

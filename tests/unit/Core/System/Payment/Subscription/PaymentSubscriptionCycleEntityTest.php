<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionCycleCollection;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionCycleEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentSubscriptionCycleEntity::class)]
class PaymentSubscriptionCycleEntityTest extends TestCase
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
        yield 'entity name' => ['payment_subscription_cycle', PaymentSubscriptionCycleEntity::ENTITY_NAME];
        yield 'api alias' => ['payment_subscription_cycle_collection', (new PaymentSubscriptionCycleCollection())->getApiAlias()];
    }

    public function testPropertiesRoundTrip(): void
    {
        $entity = new PaymentSubscriptionCycleEntity();
        $entity->id = Uuid::randomHex();
        $entity->subscriptionId = Uuid::randomHex();
        $entity->cycleNo = 3;
        $entity->periodStart = new \DateTimeImmutable('2026-11-01 00:00:00');
        $entity->periodEnd = new \DateTimeImmutable('2026-11-30 23:59:59');
        $entity->amount = 1990;
        $entity->currency = 'CNY';
        $entity->stateId = Uuid::randomHex();
        $entity->outTradeNo = 'PS1A2B3C4D-3';
        $entity->failureCode = 'ACQ.BUYER_BALANCE_NOT_ENOUGH';
        $entity->failureClass = PaymentSubscriptionCycleEntity::FAILURE_CLASS_RETRYABLE;
        $entity->nextRetryAt = new \DateTimeImmutable('2026-11-02 09:00:00');

        static::assertSame(3, $entity->cycleNo);
        static::assertSame('CNY', $entity->currency);
        static::assertSame(1990, $entity->amount);
        static::assertSame('PS1A2B3C4D-3', $entity->outTradeNo);
        static::assertSame(PaymentSubscriptionCycleEntity::FAILURE_CLASS_RETRYABLE, $entity->failureClass);
        static::assertSame('2026-11-02 09:00:00', $entity->nextRetryAt->format('Y-m-d H:i:s'));
    }

    public function testAttemptCountDefaultsToZero(): void
    {
        $entity = new PaymentSubscriptionCycleEntity();

        static::assertSame(0, $entity->attemptCount);
    }

    public function testCollectionHoldsEntitiesById(): void
    {
        $entity = new PaymentSubscriptionCycleEntity();
        $id = Uuid::randomHex();
        $entity->id = $id;
        $entity->cycleNo = 1;

        $collection = new PaymentSubscriptionCycleCollection();
        $collection->set($id, $entity);

        static::assertSame($entity, $collection->get($id));
    }
}

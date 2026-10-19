<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Subscription\PaymentPlanCollection;
use Shopwell\Core\System\Payment\Subscription\PaymentPlanEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentPlanEntity::class)]
class PaymentPlanEntityTest extends TestCase
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
        yield 'entity name' => ['payment_plan', PaymentPlanEntity::ENTITY_NAME];
        yield 'api alias' => ['payment_plan_collection', (new PaymentPlanCollection())->getApiAlias()];
    }

    public function testPropertiesRoundTrip(): void
    {
        $entity = new PaymentPlanEntity();
        $entity->id = Uuid::randomHex();
        $entity->technicalName = 'monthly-basic';
        $entity->salesChannelId = Uuid::randomHex();
        $entity->billingMode = PaymentPlanEntity::BILLING_MODE_MERCHANT_INITIATED;
        $entity->provider = 'alipay';
        $entity->channel = 'alipay_h5';
        $entity->intervalUnit = PaymentPlanEntity::INTERVAL_UNIT_MONTH;
        $entity->intervalCount = 1;
        $entity->amount = 1990;
        $entity->currencyId = Uuid::randomHex();
        $entity->trialDays = 7;
        $entity->providerPlanRef = null;
        $entity->providerPlanSnapshot = ['period_rule_params' => ['period' => 'MONTH']];

        static::assertSame('monthly-basic', $entity->technicalName);
        static::assertSame(PaymentPlanEntity::BILLING_MODE_MERCHANT_INITIATED, $entity->billingMode);
        static::assertSame(PaymentPlanEntity::INTERVAL_UNIT_MONTH, $entity->intervalUnit);
        static::assertSame(1990, $entity->amount);
        static::assertSame(7, $entity->trialDays);
        static::assertNull($entity->providerPlanRef);
        static::assertSame(['period_rule_params' => ['period' => 'MONTH']], $entity->providerPlanSnapshot);
    }

    public function testCollectionHoldsEntitiesById(): void
    {
        $entity = new PaymentPlanEntity();
        $id = Uuid::randomHex();
        $entity->id = $id;
        $entity->technicalName = 'yearly-pro';

        $collection = new PaymentPlanCollection();
        $collection->set($id, $entity);

        static::assertSame($entity, $collection->get($id));
    }
}

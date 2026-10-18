<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Subscription\PaymentMandateCollection;
use Shopwell\Core\System\Payment\Subscription\PaymentMandateEntity;
use Shopwell\Core\System\Payment\Subscription\PaymentMandateStates;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentMandateEntity::class)]
class PaymentMandateEntityTest extends TestCase
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
        yield 'entity name' => ['payment_mandate', PaymentMandateEntity::ENTITY_NAME];
        yield 'api alias' => ['payment_mandate_collection', (new PaymentMandateCollection())->getApiAlias()];
        yield 'state machine' => ['payment_mandate.status', PaymentMandateStates::STATE_MACHINE];
    }

    public function testPropertiesRoundTrip(): void
    {
        $entity = new PaymentMandateEntity();
        $entity->id = Uuid::randomHex();
        $entity->agreementNo = 'PA1A2B3C4D';
        $entity->outAgreementNo = 'out-agreement-1';
        $entity->providerAgreementNo = null;
        $entity->customerId = Uuid::randomHex();
        $entity->planId = Uuid::randomHex();
        $entity->provider = 'wechat';
        $entity->channel = 'wechat_h5';
        $entity->termsSnapshot = ['max_times' => 12];
        $entity->statusId = Uuid::randomHex();
        $entity->singleLimit = 10000;
        $entity->templateType = PaymentMandateEntity::TEMPLATE_TYPE_AUTO_RENEW;
        $entity->signedAt = new \DateTimeImmutable('2026-10-17 08:00:00');

        static::assertSame('PA1A2B3C4D', $entity->agreementNo);
        static::assertSame('out-agreement-1', $entity->outAgreementNo);
        static::assertNull($entity->providerAgreementNo);
        static::assertSame(['max_times' => 12], $entity->termsSnapshot);
        static::assertSame(PaymentMandateEntity::TEMPLATE_TYPE_AUTO_RENEW, $entity->templateType);
        static::assertSame(10000, $entity->singleLimit);
        static::assertSame('2026-10-17 08:00:00', $entity->signedAt->format('Y-m-d H:i:s'));
    }

    public function testReusableDefaultsToFalse(): void
    {
        $entity = new PaymentMandateEntity();

        static::assertFalse($entity->reusable);

        $entity->reusable = true;

        static::assertTrue($entity->reusable);
    }

    public function testCollectionHoldsEntitiesById(): void
    {
        $entity = new PaymentMandateEntity();
        $id = Uuid::randomHex();
        $entity->id = $id;
        $entity->agreementNo = 'PAZZZZZZZZ';

        $collection = new PaymentMandateCollection();
        $collection->set($id, $entity);

        static::assertSame($entity, $collection->get($id));
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\MembershipOrder\MembershipOrderCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\MembershipOrder\MembershipOrderEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\MembershipPlan\MembershipPlanCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\MembershipPlan\MembershipPlanEntity;
use Shopwell\Core\Checkout\Loyalty\LoyaltyException;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyLedger;
use Shopwell\Core\Checkout\Loyalty\Service\MembershipService;
use Shopwell\Core\Checkout\Payment\Storefront\StorefrontPaymentService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;

/**
 * The purchase of a membership and its activation are tested here, the period itself is written by
 * {@see \Shopwell\Tests\Unit\Core\Checkout\Loyalty\Service\LoyaltyLedgerTest}.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(MembershipService::class)]
class MembershipServiceTest extends TestCase
{
    private const CUSTOMER_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const PLAN_ID = 'b1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const ORDER_ID = 'c1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const TIER_ID = 'd1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const CURRENCY_ID = 'e1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const NOW = '2026-06-01 12:00:00';

    /**
     * @var StaticEntityRepository<MembershipPlanCollection>
     */
    private StaticEntityRepository $planRepository;

    /**
     * @var StaticEntityRepository<MembershipOrderCollection>
     */
    private StaticEntityRepository $orderRepository;

    private LoyaltyLedger $loyaltyLedger;

    private StorefrontPaymentService&Stub $payments;

    private Connection&Stub $connection;

    private MembershipService $membershipService;

    private Context $context;

    protected function setUp(): void
    {
        $this->planRepository = StaticEntityRepository::of(MembershipPlanCollection::class);
        $this->orderRepository = StaticEntityRepository::of(MembershipOrderCollection::class);
        $this->loyaltyLedger = static::createStub(LoyaltyLedger::class);
        $this->payments = static::createStub(StorefrontPaymentService::class);
        $this->connection = static::createStub(Connection::class);
        $this->context = Context::createDefaultContext();

        $this->connection->method('transactional')->willReturnCallback(static fn (callable $callback) => $callback());

        $this->membershipService = new MembershipService(
            $this->planRepository,
            $this->orderRepository,
            $this->loyaltyLedger,
            $this->payments,
            $this->connection,
            new MockClock(self::NOW),
        );
    }

    public function testPlansOfTheCurrencyArePresentedWithTheirTier(): void
    {
        $tier = new LoyaltyTierEntity();
        $tier->setId(self::TIER_ID);
        $tier->setName('金卡');
        $tier->setLevel(3);
        $tier->setPointsMultiplier(2.0);
        $tier->setDiscountPercent(5.0);
        $tier->setFreeShipping(true);

        $plan = $this->createPlan();
        $plan->setLoyaltyTier($tier);

        $this->planRepository->addSearch(new MembershipPlanCollection([$plan]));

        $plans = $this->membershipService->plans(self::CURRENCY_ID, $this->context);

        static::assertCount(1, $plans);
        static::assertSame(self::PLAN_ID, $plans[0]['id']);
        static::assertSame('年度金卡', $plans[0]['name']);
        static::assertSame(199.0, $plans[0]['price']);
        static::assertSame(365, $plans[0]['durationDays']);
        static::assertSame(self::TIER_ID, $plans[0]['tier']['id']);
        static::assertSame(2.0, $plans[0]['tier']['pointsMultiplier']);
    }

    public function testPurchaseCopiesThePlanAndOpensThePayment(): void
    {
        $this->planRepository->addSearch(new MembershipPlanCollection([$this->createPlan()]));

        $payments = $this->createMock(StorefrontPaymentService::class);
        $payments->expects($this->once())
            ->method('open')
            ->with(
                static::isString(),
                'alipay_h5',
                19900,
                'CNY',
                '年度金卡',
                static::stringStartsWith(MembershipService::ATTACH_PREFIX),
                'https://shop.test/account/loyalty',
                '10.0.0.1',
                'web',
            )
            ->willReturn(['payment_no' => 'PAY-1', 'channel' => 'alipay_h5', 'params' => ['url' => 'https://pay.test']]);

        $result = $this->service(payments: $payments)->purchase(
            self::CUSTOMER_ID,
            self::PLAN_ID,
            'alipay_h5',
            self::CURRENCY_ID,
            'cny',
            'https://shop.test/account/loyalty',
            '10.0.0.1',
            'web',
            $this->context,
        );

        static::assertSame(199.0, $result['amount']);
        static::assertSame('CNY', $result['currency']);
        static::assertSame('PAY-1', $result['payment_no']);

        $created = $this->orderRepository->creates[0][0];
        static::assertSame($result['id'], $created['id']);
        static::assertSame(self::TIER_ID, $created['loyaltyTierId']);
        static::assertSame('年度金卡', $created['planName']);
        static::assertSame(365, $created['durationDays']);
        static::assertSame(MembershipOrderEntity::STATE_OPEN, $created['state']);

        static::assertSame('PAY-1', $this->orderRepository->updates[0][0]['paymentNo']);
        static::assertSame('alipay_h5', $this->orderRepository->updates[0][0]['channelTechnicalName']);
    }

    public function testPurchaseOfAnUnknownPlanIsRefused(): void
    {
        $this->planRepository->addSearch(new MembershipPlanCollection());

        $this->expectExceptionObject(LoyaltyException::planNotFound(self::PLAN_ID));

        $this->membershipService->purchase(self::CUSTOMER_ID, self::PLAN_ID, 'alipay_h5', self::CURRENCY_ID, 'CNY', null, null, null, $this->context);
    }

    public function testPurchaseOfAnInactivePlanIsRefused(): void
    {
        $plan = $this->createPlan();
        $plan->setActive(false);

        $this->planRepository->addSearch(new MembershipPlanCollection([$plan]));

        $this->expectExceptionObject(LoyaltyException::planNotPurchasable(self::PLAN_ID));

        $this->membershipService->purchase(self::CUSTOMER_ID, self::PLAN_ID, 'alipay_h5', self::CURRENCY_ID, 'CNY', null, null, null, $this->context);
    }

    public function testPurchaseInAnotherCurrencyIsRefused(): void
    {
        $this->planRepository->addSearch(new MembershipPlanCollection([$this->createPlan()]));

        $this->expectExceptionObject(LoyaltyException::planNotPurchasable(self::PLAN_ID));

        $this->membershipService->purchase(self::CUSTOMER_ID, self::PLAN_ID, 'alipay_h5', 'other-currency-id', 'CNY', null, null, null, $this->context);
    }

    public function testAFailedPaymentKeepsTheOrderVisible(): void
    {
        $this->planRepository->addSearch(new MembershipPlanCollection([$this->createPlan()]));
        $this->payments->method('open')->willThrowException(new \RuntimeException('channel is down'));

        try {
            $this->membershipService->purchase(self::CUSTOMER_ID, self::PLAN_ID, 'alipay_h5', self::CURRENCY_ID, 'CNY', null, null, null, $this->context);

            static::fail('The exception of the channel has to reach the caller');
        } catch (\RuntimeException) {
            // expected
        }

        static::assertSame(MembershipOrderEntity::STATE_FAILED, $this->orderRepository->updates[0][0]['state']);
    }

    public function testOrdersArePresentedNewestFirst(): void
    {
        $order = new MembershipOrderEntity();
        $order->setId(self::ORDER_ID);
        $order->setPlanName('年度金卡');
        $order->setAmount(199.0);
        $order->setCurrency('CNY');
        $order->setDurationDays(365);
        $order->setState(MembershipOrderEntity::STATE_ACTIVATED);
        $order->setStartsAt(new \DateTimeImmutable(self::NOW));
        $order->setExpiresAt(new \DateTimeImmutable('2027-06-01 12:00:00'));

        $this->orderRepository->addSearch(new MembershipOrderCollection([$order]));

        $orders = $this->membershipService->orders(self::CUSTOMER_ID, 5, $this->context);

        static::assertCount(1, $orders);
        static::assertSame(MembershipOrderEntity::STATE_ACTIVATED, $orders[0]['state']);
        static::assertSame('2027-06-01T12:00:00+00:00', $orders[0]['expiresAt']);
    }

    public function testSettleActivatesTheMembershipOnce(): void
    {
        $this->connection->method('fetchAssociative')->willReturn($this->orderRow(199.0, MembershipOrderEntity::STATE_OPEN));

        $loyaltyLedger = $this->createMock(LoyaltyLedger::class);
        $loyaltyLedger->expects($this->once())
            ->method('activateMembership')
            ->with(self::CUSTOMER_ID, self::TIER_ID, 365, $this->context)
            ->willReturn([
                'startsAt' => new \DateTimeImmutable(self::NOW),
                'expiresAt' => new \DateTimeImmutable('2027-06-01 12:00:00'),
            ]);

        static::assertTrue($this->service(loyaltyLedger: $loyaltyLedger)->settle(self::ORDER_ID, 19900, 'PAY-1', $this->context));

        $update = $this->orderRepository->updates[0][0];
        static::assertSame(MembershipOrderEntity::STATE_ACTIVATED, $update['state']);
        static::assertSame('PAY-1', $update['paymentNo']);
        static::assertEquals(new \DateTimeImmutable('2027-06-01 12:00:00'), $update['expiresAt']);
    }

    public function testSettleDoesNothingForAnActivatedOrder(): void
    {
        $this->connection->method('fetchAssociative')->willReturn($this->orderRow(199.0, MembershipOrderEntity::STATE_ACTIVATED));

        $loyaltyLedger = $this->createMock(LoyaltyLedger::class);
        $loyaltyLedger->expects($this->never())->method('activateMembership');

        static::assertFalse($this->service(loyaltyLedger: $loyaltyLedger)->settle(self::ORDER_ID, 19900, 'PAY-1', $this->context));
        static::assertSame([], $this->orderRepository->updates);
    }

    public function testSettleRefusesAnAmountTheCustomerDidNotAgreeTo(): void
    {
        $this->connection->method('fetchAssociative')->willReturn($this->orderRow(199.0, MembershipOrderEntity::STATE_OPEN));

        $loyaltyLedger = $this->createMock(LoyaltyLedger::class);
        $loyaltyLedger->expects($this->never())->method('activateMembership');

        static::assertFalse($this->service(loyaltyLedger: $loyaltyLedger)->settle(self::ORDER_ID, 1, 'PAY-1', $this->context));
        static::assertSame(MembershipOrderEntity::STATE_FAILED, $this->orderRepository->updates[0][0]['state']);
    }

    /**
     * A tier that was removed while the payment was open cannot be granted, the payment is not lost
     * but stays failed for the operator.
     */
    public function testSettleRefusesAnOrderWhoseTierIsGone(): void
    {
        $this->connection->method('fetchAssociative')->willReturn($this->orderRow(199.0, MembershipOrderEntity::STATE_OPEN, null));

        $loyaltyLedger = $this->createMock(LoyaltyLedger::class);
        $loyaltyLedger->expects($this->never())->method('activateMembership');

        static::assertFalse($this->service(loyaltyLedger: $loyaltyLedger)->settle(self::ORDER_ID, 19900, 'PAY-1', $this->context));
        static::assertSame(MembershipOrderEntity::STATE_FAILED, $this->orderRepository->updates[0][0]['state']);
    }

    public function testSettleIgnoresAnUnknownOrder(): void
    {
        $this->connection->method('fetchAssociative')->willReturn(false);

        static::assertFalse($this->membershipService->settle(self::ORDER_ID, 19900, 'PAY-1', $this->context));
        static::assertSame([], $this->orderRepository->updates);
    }

    /**
     * The service under test with the doubles the scenario needs - the shared ones of setUp() only
     * answer, they are no mocks.
     */
    private function service(
        ?LoyaltyLedger $loyaltyLedger = null,
        ?StorefrontPaymentService $payments = null,
    ): MembershipService {
        return new MembershipService(
            $this->planRepository,
            $this->orderRepository,
            $loyaltyLedger ?? $this->loyaltyLedger,
            $payments ?? $this->payments,
            $this->connection,
            new MockClock(self::NOW),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function orderRow(float $amount, string $state, ?string $tierId = self::TIER_ID): array
    {
        return [
            'id' => self::ORDER_ID,
            'customer_id' => self::CUSTOMER_ID,
            'loyalty_tier_id' => $tierId,
            'duration_days' => 365,
            'amount' => $amount,
            'currency' => 'CNY',
            'state' => $state,
        ];
    }

    private function createPlan(): MembershipPlanEntity
    {
        $plan = new MembershipPlanEntity();
        $plan->setId(self::PLAN_ID);
        $plan->setTechnicalName('gold-yearly');
        $plan->setLoyaltyTierId(self::TIER_ID);
        $plan->setName('年度金卡');
        $plan->setDescription('一年金卡会员');
        $plan->setPrice(199.0);
        $plan->setDurationDays(365);
        $plan->setCurrencyId(self::CURRENCY_ID);
        $plan->setActive(true);

        return $plan;
    }
}

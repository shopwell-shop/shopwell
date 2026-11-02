<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Membership\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Membership\Entity\MembershipOrder\MembershipOrderCollection;
use Shopwell\Core\Checkout\Membership\Entity\MembershipOrder\MembershipOrderEntity;
use Shopwell\Core\Checkout\Membership\Entity\MembershipPlan\MembershipPlanCollection;
use Shopwell\Core\Checkout\Membership\Entity\MembershipPlan\MembershipPlanEntity;
use Shopwell\Core\Checkout\Membership\MembershipException;
use Shopwell\Core\Checkout\Membership\Service\MembershipPlanService;
use Shopwell\Core\Checkout\Membership\Service\MembershipPurchaseService;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseAttach;
use Shopwell\Core\Checkout\Payment\Storefront\StorefrontPaymentService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * The purchase writes the order and hands it to the gateway; that a paid order becomes a membership is
 * tested in {@see \Shopwell\Tests\Unit\Core\Checkout\Membership\Purchase\MembershipOrderPurchaseTest}
 * and {@see \Shopwell\Tests\Unit\Core\Checkout\Payment\Storefront\Settlement\PurchaseSettlementTest}.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(MembershipPurchaseService::class)]
class MembershipPurchaseServiceTest extends TestCase
{
    private const CUSTOMER_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const PLAN_ID = 'b1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const ORDER_ID = 'c1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const TIER_ID = 'd1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const CURRENCY_ID = 'e1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const ATTACH_PREFIX = 'membership:';

    /**
     * @var StaticEntityRepository<MembershipPlanCollection>
     */
    private StaticEntityRepository $planRepository;

    /**
     * @var StaticEntityRepository<MembershipOrderCollection>
     */
    private StaticEntityRepository $orderRepository;

    private StorefrontPaymentService&Stub $payments;

    private Context $context;

    protected function setUp(): void
    {
        $this->planRepository = StaticEntityRepository::of(MembershipPlanCollection::class);
        $this->orderRepository = StaticEntityRepository::of(MembershipOrderCollection::class);
        $this->payments = static::createStub(StorefrontPaymentService::class);
        $this->context = Context::createDefaultContext();
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
                static::stringStartsWith(self::ATTACH_PREFIX),
                'https://shop.test/account/loyalty',
                '10.0.0.1',
                'web',
            )
            ->willReturn(['payment_no' => 'PAY-1', 'channel' => 'alipay_h5', 'params' => ['url' => 'https://pay.test']]);

        $result = $this->service($payments)->purchase(
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

    /**
     * The marker on the payment is what the settlement reads back, so it has to carry the very id the
     * order was written with.
     */
    public function testThePaymentCarriesTheMarkerOfTheOrderItPays(): void
    {
        $this->planRepository->addSearch(new MembershipPlanCollection([$this->createPlan()]));

        $attachedTo = null;

        $payments = static::createStub(StorefrontPaymentService::class);
        $payments->method('open')->willReturnCallback(
            static function (string $outTradeNo, string $channel, int $amount, string $currency, string $subject, string $attach) use (&$attachedTo): array {
                $attachedTo = ['outTradeNo' => $outTradeNo, 'attach' => $attach];

                return ['payment_no' => 'PAY-1', 'channel' => $channel, 'params' => []];
            }
        );

        $result = $this->service($payments)->purchase(self::CUSTOMER_ID, self::PLAN_ID, 'alipay_h5', self::CURRENCY_ID, 'CNY', null, null, null, $this->context);

        static::assertIsArray($attachedTo);
        static::assertSame($result['id'], $attachedTo['outTradeNo']);
        static::assertSame(self::ATTACH_PREFIX . $result['id'], $attachedTo['attach']);
        static::assertSame($result['id'], (new PurchaseAttach(self::ATTACH_PREFIX))->purchaseIdOf($attachedTo['attach']));
    }

    public function testAnUnknownPlanIsRefused(): void
    {
        $this->planRepository->addSearch(new MembershipPlanCollection());

        $this->expectExceptionObject(MembershipException::planNotFound(self::PLAN_ID));

        $this->service()->purchase(self::CUSTOMER_ID, self::PLAN_ID, 'alipay_h5', self::CURRENCY_ID, 'CNY', null, null, null, $this->context);
    }

    public function testAnInactivePlanIsRefused(): void
    {
        $plan = $this->createPlan();
        $plan->setActive(false);

        $this->planRepository->addSearch(new MembershipPlanCollection([$plan]));

        $this->expectExceptionObject(MembershipException::planNotPurchasable(self::PLAN_ID));

        $this->service()->purchase(self::CUSTOMER_ID, self::PLAN_ID, 'alipay_h5', self::CURRENCY_ID, 'CNY', null, null, null, $this->context);
    }

    /**
     * A channel that is down must not swallow the attempt: the order stays visible as failed, so the
     * operator can find the customer who tried to buy and could not.
     */
    public function testAFailedPaymentKeepsTheOrderVisible(): void
    {
        $this->planRepository->addSearch(new MembershipPlanCollection([$this->createPlan()]));

        $this->payments->method('open')->willThrowException(new \RuntimeException('channel is down'));

        try {
            $this->service()->purchase(self::CUSTOMER_ID, self::PLAN_ID, 'alipay_h5', self::CURRENCY_ID, 'CNY', null, null, null, $this->context);

            static::fail('The exception of the channel has to reach the caller');
        } catch (\RuntimeException) {
            // expected
        }

        static::assertSame(MembershipOrderEntity::STATE_FAILED, $this->orderRepository->updates[0][0]['state']);
    }

    public function testRecentOrdersAnswersTheOrdersOfTheCustomer(): void
    {
        $order = new MembershipOrderEntity();
        $order->setId(self::ORDER_ID);
        $order->setPlanName('年度金卡');

        $this->orderRepository->addSearch(new MembershipOrderCollection([$order]));

        $orders = $this->service()->recentOrders(self::CUSTOMER_ID, 5, $this->context);

        static::assertCount(1, $orders);
        static::assertSame(self::ORDER_ID, $orders[0]->getId());
    }

    private function service(?StorefrontPaymentService $payments = null): MembershipPurchaseService
    {
        return new MembershipPurchaseService(
            $this->orderRepository,
            new MembershipPlanService($this->planRepository),
            $payments ?? $this->payments,
            new PurchaseAttach(self::ATTACH_PREFIX),
        );
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

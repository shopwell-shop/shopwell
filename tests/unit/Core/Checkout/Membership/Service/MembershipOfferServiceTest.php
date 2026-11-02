<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Membership\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Membership\Entity\MembershipOrder\MembershipOrderCollection;
use Shopwell\Core\Checkout\Membership\Entity\MembershipOrder\MembershipOrderEntity;
use Shopwell\Core\Checkout\Membership\Entity\MembershipPlan\MembershipPlanCollection;
use Shopwell\Core\Checkout\Membership\Entity\MembershipPlan\MembershipPlanEntity;
use Shopwell\Core\Checkout\Membership\SalesChannel\MembershipPresenter;
use Shopwell\Core\Checkout\Membership\Service\MembershipOfferService;
use Shopwell\Core\Checkout\Membership\Service\MembershipPlanService;
use Shopwell\Core\Checkout\Membership\Service\MembershipPurchaseService;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseAttach;
use Shopwell\Core\Checkout\Payment\Storefront\StorefrontPaymentService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(MembershipOfferService::class)]
class MembershipOfferServiceTest extends TestCase
{
    private const CUSTOMER_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const PLAN_ID = 'b1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const ORDER_ID = 'c1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const CURRENCY_ID = 'e1b2c3d4e5f60718293a4b5c6d7e8f90';

    /**
     * @var StaticEntityRepository<MembershipPlanCollection>
     */
    private StaticEntityRepository $planRepository;

    /**
     * @var StaticEntityRepository<MembershipOrderCollection>
     */
    private StaticEntityRepository $orderRepository;

    private StorefrontPaymentService&Stub $payments;

    private MembershipOfferService $offer;

    private Context $context;

    protected function setUp(): void
    {
        $this->planRepository = StaticEntityRepository::of(MembershipPlanCollection::class);
        $this->orderRepository = StaticEntityRepository::of(MembershipOrderCollection::class);
        $this->payments = static::createStub(StorefrontPaymentService::class);
        $this->context = Context::createDefaultContext();

        $this->payments->method('channels')->willReturn([
            ['channel' => 'alipay_h5', 'label' => '支付宝', 'provider' => 'alipay', 'provider_label' => '支付宝', 'flow' => 'redirect'],
        ]);

        $this->offer = new MembershipOfferService(
            new MembershipPlanService($this->planRepository),
            new MembershipPurchaseService($this->orderRepository, new MembershipPlanService($this->planRepository), $this->payments, new PurchaseAttach('membership:')),
            new MembershipPresenter(),
            $this->payments,
        );
    }

    public function testItAnswersPlansChannelsAndOrdersInOneShape(): void
    {
        $plan = new MembershipPlanEntity();
        $plan->setId(self::PLAN_ID);
        $plan->setTechnicalName('gold-yearly');
        $plan->setName('年度金卡');
        $plan->setPrice(199.0);
        $plan->setDurationDays(365);
        $plan->setCurrencyId(self::CURRENCY_ID);
        $plan->setActive(true);

        $order = new MembershipOrderEntity();
        $order->setId(self::ORDER_ID);
        $order->setPlanName('年度金卡');
        $order->setAmount(199.0);
        $order->setCurrency('CNY');
        $order->setDurationDays(365);
        $order->setState(MembershipOrderEntity::STATE_ACTIVATED);

        $this->planRepository->addSearch(new MembershipPlanCollection([$plan]));
        $this->orderRepository->addSearch(new MembershipOrderCollection([$order]));

        $offer = $this->offer->forCustomer(self::CUSTOMER_ID, self::CURRENCY_ID, 'CNY', 5, $this->context);

        static::assertSame(['plans', 'channels', 'orders'], array_keys($offer));
        static::assertSame(self::PLAN_ID, $offer['plans'][0]['id']);
        static::assertSame('alipay_h5', $offer['channels'][0]['channel']);
        static::assertSame(self::ORDER_ID, $offer['orders'][0]['id']);
    }

    public function testACustomerWithoutAPurchaseStillSeesTheOffer(): void
    {
        $this->planRepository->addSearch(new MembershipPlanCollection());
        $this->orderRepository->addSearch(new MembershipOrderCollection());

        $offer = $this->offer->forCustomer(self::CUSTOMER_ID, self::CURRENCY_ID, 'CNY', 5, $this->context);

        static::assertSame([], $offer['plans']);
        static::assertSame([], $offer['orders']);
        static::assertCount(1, $offer['channels']);
    }
}

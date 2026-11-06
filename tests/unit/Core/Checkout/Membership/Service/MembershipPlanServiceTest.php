<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Membership\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierEntity;
use Shopwell\Core\Checkout\Membership\Entity\MembershipPlan\MembershipPlanCollection;
use Shopwell\Core\Checkout\Membership\Entity\MembershipPlan\MembershipPlanEntity;
use Shopwell\Core\Checkout\Membership\MembershipException;
use Shopwell\Core\Checkout\Membership\Service\MembershipPlanService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * What a customer may buy is decided here, everything about a running membership is decided by the
 * purchase and its settlement.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(MembershipPlanService::class)]
class MembershipPlanServiceTest extends TestCase
{
    private const PLAN_ID = 'b1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const TIER_ID = 'd1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const CURRENCY_ID = 'e1b2c3d4e5f60718293a4b5c6d7e8f90';

    /**
     * @var StaticEntityRepository<MembershipPlanCollection>
     */
    private StaticEntityRepository $planRepository;

    private MembershipPlanService $plans;

    private Context $context;

    protected function setUp(): void
    {
        $this->planRepository = StaticEntityRepository::of(MembershipPlanCollection::class);
        $this->plans = new MembershipPlanService($this->planRepository);
        $this->context = Context::createDefaultContext();
    }

    public function testPurchasableAnswersThePlansOfTheCurrencyWithTheirTier(): void
    {
        $tier = new LoyaltyTierEntity();
        $tier->setId(self::TIER_ID);
        $tier->setName('金卡');

        $plan = $this->createPlan();
        $plan->setLoyaltyTier($tier);

        $this->planRepository->addSearch(new MembershipPlanCollection([$plan]));

        $plans = $this->plans->purchasable(self::CURRENCY_ID, $this->context);

        static::assertCount(1, $plans);
        static::assertSame(self::PLAN_ID, $plans[0]->getId());
        static::assertSame($tier, $plans[0]->getLoyaltyTier());
    }

    public function testFindPurchasableAnswersThePlan(): void
    {
        $this->planRepository->addSearch(new MembershipPlanCollection([$this->createPlan()]));

        static::assertSame(self::PLAN_ID, $this->plans->findPurchasable(self::PLAN_ID, self::CURRENCY_ID, $this->context)->getId());
    }

    public function testAnUnknownPlanIsRefused(): void
    {
        $this->planRepository->addSearch(new MembershipPlanCollection());

        $this->expectExceptionObject(MembershipException::planNotFound(self::PLAN_ID));

        $this->plans->findPurchasable(self::PLAN_ID, self::CURRENCY_ID, $this->context);
    }

    public function testAnInactivePlanIsRefused(): void
    {
        $plan = $this->createPlan();
        $plan->setActive(false);

        $this->planRepository->addSearch(new MembershipPlanCollection([$plan]));

        $this->expectExceptionObject(MembershipException::planNotPurchasable(self::PLAN_ID));

        $this->plans->findPurchasable(self::PLAN_ID, self::CURRENCY_ID, $this->context);
    }

    /**
     * A price is only a price in its own currency, so a plan of another one is not offered instead of
     * being sold at a converted rate the shop never decided.
     */
    public function testAPlanOfAnotherCurrencyIsRefused(): void
    {
        $this->planRepository->addSearch(new MembershipPlanCollection([$this->createPlan()]));

        $this->expectExceptionObject(MembershipException::planNotPurchasable(self::PLAN_ID));

        $this->plans->findPurchasable(self::PLAN_ID, 'other-currency-id', $this->context);
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

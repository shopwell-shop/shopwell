<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Membership\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTierBenefit\LoyaltyTierBenefitCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTierBenefit\LoyaltyTierBenefitEntity;
use Shopwell\Core\Checkout\Membership\Entity\MembershipPlan\MembershipPlanEntity;
use Shopwell\Core\Checkout\Membership\Entity\MembershipPurchase\MembershipPurchaseEntity;
use Shopwell\Core\Checkout\Membership\SalesChannel\MembershipPresenter;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(MembershipPresenter::class)]
class MembershipPresenterTest extends TestCase
{
    private const PLAN_ID = 'b1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const PURCHASE_ID = 'c1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const TIER_ID = 'd1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const CURRENCY_ID = 'e1b2c3d4e5f60718293a4b5c6d7e8f90';

    private MembershipPresenter $presenter;

    protected function setUp(): void
    {
        $this->presenter = new MembershipPresenter();
    }

    public function testPlanIsPresentedWithWhatItGrants(): void
    {
        $tier = new LoyaltyTierEntity();
        $tier->setId(self::TIER_ID);
        $tier->setName('金卡');
        $tier->setLevel(3);
        $tier->setBenefits($this->benefits());

        $plan = new MembershipPlanEntity();
        $plan->setId(self::PLAN_ID);
        $plan->setTechnicalName('gold-yearly');
        $plan->setName('年度金卡');
        $plan->setDescription('一年金卡会员');
        $plan->setPrice(199.0);
        $plan->setDurationDays(365);
        $plan->setCurrencyId(self::CURRENCY_ID);
        $plan->setLoyaltyTier($tier);

        $presented = $this->presenter->plan($plan);

        static::assertSame(self::PLAN_ID, $presented['id']);
        static::assertSame('gold-yearly', $presented['technicalName']);
        static::assertSame('年度金卡', $presented['name']);
        static::assertSame(199.0, $presented['price']);
        static::assertSame(365, $presented['durationDays']);
        static::assertSame(self::CURRENCY_ID, $presented['currencyId']);
        static::assertSame(self::TIER_ID, $presented['tier']['id']);
        static::assertSame(
            [
                'earn_multiplier' => 2.0,
                'cart_discount' => 5.0,
                'free_shipping' => 1.0,
            ],
            $presented['tier']['benefits']
        );
    }

    /**
     * The tier is an association, so a plan that was loaded without it is presented without one instead
     * of failing the whole page.
     */
    public function testPlanWithoutALoadedTierIsPresentedWithoutOne(): void
    {
        $plan = new MembershipPlanEntity();
        $plan->setId(self::PLAN_ID);
        $plan->setTechnicalName('gold-yearly');
        $plan->setName('年度金卡');
        $plan->setPrice(199.0);
        $plan->setDurationDays(365);
        $plan->setCurrencyId(self::CURRENCY_ID);

        static::assertNull($this->presenter->plan($plan)['tier']);
    }

    public function testPurchaseIsPresentedWithItsPeriod(): void
    {
        $purchase = new MembershipPurchaseEntity();
        $purchase->setId(self::PURCHASE_ID);
        $purchase->setPlanName('年度金卡');
        $purchase->setAmount(199.0);
        $purchase->setCurrency('CNY');
        $purchase->setDurationDays(365);
        $purchase->setState(MembershipPurchaseEntity::STATE_ACTIVATED);
        $purchase->setStartsAt(new \DateTimeImmutable('2026-06-01 12:00:00'));
        $purchase->setExpiresAt(new \DateTimeImmutable('2027-06-01 12:00:00'));

        $presented = $this->presenter->purchase($purchase);

        static::assertSame(self::PURCHASE_ID, $presented['id']);
        static::assertSame(MembershipPurchaseEntity::STATE_ACTIVATED, $presented['state']);
        static::assertSame('2026-06-01T12:00:00+00:00', $presented['startsAt']);
        static::assertSame('2027-06-01T12:00:00+00:00', $presented['expiresAt']);
        static::assertNull($presented['createdAt']);
    }
}

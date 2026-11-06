<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTierBenefit\LoyaltyTierBenefitCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTierBenefit\LoyaltyTierBenefitEntity;
use Shopwell\Core\Checkout\Loyalty\LoyaltyBenefitTypes;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyTierBenefits;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * The value object is what every consumer talks to, so its job is to make an absent benefit an answer
 * and not a check: "no multiplier row" has to read as 1.0, "no discount row" as 0.0. The tests pin
 * that down together with the two rules that keep a hand built collection from producing a surprising
 * total - display order follows `position`, and a duplicate type keeps the first row.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoyaltyTierBenefits::class)]
class LoyaltyTierBenefitsTest extends TestCase
{
    public function testATierWithoutRowsGrantsNothing(): void
    {
        $benefits = LoyaltyTierBenefits::none();

        static::assertTrue($benefits->isEmpty());
        static::assertSame([], $benefits->all());
        static::assertSame(1.0, $benefits->earnMultiplier());
        static::assertSame(0.0, $benefits->cartDiscountPercent());
        static::assertSame(0.0, $benefits->birthdayGiftAmount());
        static::assertFalse($benefits->grantsFreeShipping());
        static::assertNull($benefits->pointsExpiryDays());
        static::assertFalse($benefits->has(LoyaltyBenefitTypes::EARN_MULTIPLIER));
        static::assertNull($benefits->value(LoyaltyBenefitTypes::EARN_MULTIPLIER));
    }

    public function testItReadsEveryTypeItWasBuiltFrom(): void
    {
        $benefits = $this->benefits([
            [LoyaltyBenefitTypes::EARN_MULTIPLIER, 1.5, 10],
            [LoyaltyBenefitTypes::CART_DISCOUNT, 5.0, 20],
            [LoyaltyBenefitTypes::FREE_SHIPPING, 1.0, 30],
            [LoyaltyBenefitTypes::BIRTHDAY_GIFT, 20.0, 40],
            [LoyaltyBenefitTypes::POINTS_EXPIRY_DAYS, 730, 50],
        ]);

        static::assertFalse($benefits->isEmpty());
        static::assertSame(1.5, $benefits->earnMultiplier());
        static::assertSame(5.0, $benefits->cartDiscountPercent());
        static::assertTrue($benefits->grantsFreeShipping());
        static::assertSame(20.0, $benefits->birthdayGiftAmount());
        static::assertSame(730, $benefits->pointsExpiryDays());
    }

    public function testTheDisplayOrderFollowsThePosition(): void
    {
        $benefits = $this->benefits([
            [LoyaltyBenefitTypes::FREE_SHIPPING, 1.0, 30],
            [LoyaltyBenefitTypes::EARN_MULTIPLIER, 2.0, 10],
            [LoyaltyBenefitTypes::CART_DISCOUNT, 5.0, 20],
        ]);

        static::assertSame([
            LoyaltyBenefitTypes::EARN_MULTIPLIER,
            LoyaltyBenefitTypes::CART_DISCOUNT,
            LoyaltyBenefitTypes::FREE_SHIPPING,
        ], array_keys($benefits->all()));
    }

    /**
     * The schema allows one row per type, a collection built by hand does not have to. Whichever row
     * comes first wins, so the total cannot depend on the iteration order of a collection.
     */
    public function testADuplicateTypeKeepsTheFirstRow(): void
    {
        $benefits = $this->benefits([
            [LoyaltyBenefitTypes::CART_DISCOUNT, 5.0, 10],
            [LoyaltyBenefitTypes::CART_DISCOUNT, 9.0, 20],
        ]);

        static::assertSame(5.0, $benefits->cartDiscountPercent());
        static::assertCount(1, $benefits->all());
    }

    /**
     * @param list<array{0: string, 1: float, 2: int}> $rows type, value, position
     */
    private function benefits(array $rows): LoyaltyTierBenefits
    {
        $collection = new LoyaltyTierBenefitCollection();

        foreach ($rows as [$type, $value, $position]) {
            $benefit = new LoyaltyTierBenefitEntity();
            $benefit->setId(Uuid::randomHex());
            $benefit->setLoyaltyTierId(Uuid::randomHex());
            $benefit->setType($type);
            $benefit->setValue($value);
            $benefit->setPosition($position);
            $collection->add($benefit);
        }

        return LoyaltyTierBenefits::of($collection);
    }
}

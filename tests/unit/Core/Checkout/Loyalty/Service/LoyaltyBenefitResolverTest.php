<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTierBenefit\LoyaltyTierBenefitCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTierBenefit\LoyaltyTierBenefitEntity;
use Shopwell\Core\Checkout\Loyalty\LoyaltyBenefitTypes;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyBenefitResolver;
use Shopwell\Core\Checkout\Loyalty\Service\TierResolver;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * The resolver is the one place that knows how a benefit is stored. A benefit is a row and nothing
 * else, so what the tests pin down is the two ways to reach the rows: read them from the association
 * the caller loaded, or ask the repository for the tier's rows. Both have to answer the same thing -
 * and a tier without rows grants nothing rather than falling back to a column that no longer exists.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoyaltyBenefitResolver::class)]
class LoyaltyBenefitResolverTest extends TestCase
{
    private const CUSTOMER_ID = 'customer-id';

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testACustomerWithoutATierGetsNoBenefits(): void
    {
        $resolver = $this->resolver(null, []);

        static::assertTrue($resolver->forCustomer(self::CUSTOMER_ID, $this->context)->isEmpty());
        static::assertSame(1.0, $resolver->earnMultiplier(self::CUSTOMER_ID, $this->context));
    }

    public function testATierWithoutRowsGrantsNothing(): void
    {
        $benefits = $this->resolver($this->tier(), [])->forCustomer(self::CUSTOMER_ID, $this->context);

        static::assertTrue($benefits->isEmpty());
        static::assertSame(1.0, $benefits->earnMultiplier());
        static::assertFalse($benefits->grantsFreeShipping());
    }

    public function testItReadsTheRowsOfTheTier(): void
    {
        $tier = $this->tier();

        $benefits = $this->resolver($tier, [
            [LoyaltyBenefitTypes::EARN_MULTIPLIER, 2.0, 10],
            [LoyaltyBenefitTypes::BIRTHDAY_GIFT, 15.0, 20],
        ])->forCustomer(self::CUSTOMER_ID, $this->context);

        static::assertSame(2.0, $benefits->earnMultiplier());
        static::assertSame(15.0, $benefits->birthdayGiftAmount());
    }

    /**
     * A tier loaded together with its association must not cause a second query - the storefront route
     * reads every tier at once and the resolver sees the loaded collection there.
     */
    public function testALoadedAssociationIsUsedInsteadOfQuerying(): void
    {
        $tier = $this->tier();

        $collection = new LoyaltyTierBenefitCollection();
        $benefit = new LoyaltyTierBenefitEntity();
        $benefit->setId(Uuid::randomHex());
        $benefit->setLoyaltyTierId($tier->getId());
        $benefit->setType(LoyaltyBenefitTypes::CART_DISCOUNT);
        $benefit->setValue(8.0);
        $collection->add($benefit);
        $tier->setBenefits($collection);

        // the repository has no answer queued: a query would fail the test
        $resolver = new LoyaltyBenefitResolver(
            StaticEntityRepository::of(LoyaltyTierBenefitCollection::class, []),
            $this->tierResolver($tier),
        );

        static::assertSame(8.0, $resolver->forCustomer(self::CUSTOMER_ID, $this->context)->cartDiscountPercent());
    }

    /**
     * @param list<array{0: string, 1: float, 2: int}> $rows type, value, position
     */
    private function resolver(?LoyaltyTierEntity $tier, array $rows): LoyaltyBenefitResolver
    {
        $collection = new LoyaltyTierBenefitCollection();

        foreach ($rows as [$type, $value, $position]) {
            $benefit = new LoyaltyTierBenefitEntity();
            $benefit->setId(Uuid::randomHex());
            $benefit->setLoyaltyTierId($tier?->getId() ?? Uuid::randomHex());
            $benefit->setType($type);
            $benefit->setValue($value);
            $benefit->setPosition($position);
            $collection->add($benefit);
        }

        return new LoyaltyBenefitResolver(
            StaticEntityRepository::of(LoyaltyTierBenefitCollection::class, [$collection]),
            $this->tierResolver($tier),
        );
    }

    private function tierResolver(?LoyaltyTierEntity $tier): TierResolver
    {
        $tiers = static::createStub(TierResolver::class);
        $tiers->method('currentTier')->willReturn($tier);

        return $tiers;
    }

    private function tier(): LoyaltyTierEntity
    {
        $tier = new LoyaltyTierEntity();
        $tier->setId(Uuid::randomHex());
        $tier->setTechnicalName('silver');
        $tier->setLevel(2);

        return $tier;
    }
}

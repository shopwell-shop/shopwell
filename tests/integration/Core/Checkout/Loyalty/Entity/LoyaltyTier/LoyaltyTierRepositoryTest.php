<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Loyalty\Entity\LoyaltyTier;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierCollection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
class LoyaltyTierRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<LoyaltyTierCollection>
     */
    private EntityRepository $repository;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('loyalty_tier.repository');
    }

    public function testCreateLoyaltyTierWithTranslation(): void
    {
        $context = Context::createDefaultContext();
        $id = Uuid::randomHex();

        $this->repository->create([[
            'id' => $id,
            'technicalName' => 'gold',
            'level' => 3,
            'minTierPoints' => 5000,
            'active' => true,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'name' => 'Gold',
                    'description' => 'The gold membership tier',
                ],
            ],
        ]], $context);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('translations');

        $tier = $this->repository->search($criteria, $context)->getEntities()->get($id);

        static::assertNotNull($tier);
        static::assertSame('gold', $tier->getTechnicalName());
        static::assertSame(3, $tier->getLevel());
        static::assertSame(5000, $tier->getMinTierPoints());
        static::assertTrue($tier->getActive());
        static::assertSame('Gold', $tier->getName());
        static::assertSame('The gold membership tier', $tier->getDescription());
        static::assertNotNull($tier->getTranslations());
    }

    /**
     * The benefits of a tier are its own rows, so they go away with it. A row that outlived its tier
     * would either be orphaned or be picked up again by a tier that reuses the id.
     */
    public function testDeletingATierDeletesItsBenefits(): void
    {
        $context = Context::createDefaultContext();
        $id = Uuid::randomHex();

        $this->repository->create([[
            'id' => $id,
            'technicalName' => 'platinum',
            'level' => 4,
            'active' => true,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'Platinum'],
            ],
        ]], $context);

        $benefitRepository = static::getContainer()->get('loyalty_tier_benefit.repository');
        $benefitRepository->create([[
            'loyaltyTierId' => $id,
            'type' => 'earn_multiplier',
            'value' => 2.0,
            'position' => 10,
        ]], $context);

        $this->repository->delete([['id' => $id]], $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('loyaltyTierId', $id));

        static::assertCount(0, $benefitRepository->search($criteria, $context)->getEntities());
    }

    public function testUpdateTranslation(): void
    {
        $context = Context::createDefaultContext();
        $id = Uuid::randomHex();

        $this->repository->create([[
            'id' => $id,
            'technicalName' => 'silver',
            'level' => 2,
            'active' => true,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'Silver'],
            ],
        ]], $context);

        $this->repository->update([[
            'id' => $id,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'Silver Plus'],
            ],
        ]], $context);

        $criteria = new Criteria([$id]);

        $tier = $this->repository->search($criteria, $context)->getEntities()->get($id);

        static::assertNotNull($tier);
        static::assertSame('Silver Plus', $tier->getName());
    }

    public function testLoyaltyTierCanBeDeleted(): void
    {
        $context = Context::createDefaultContext();
        $id = Uuid::randomHex();

        $this->repository->create([[
            'id' => $id,
            'technicalName' => 'bronze',
            'level' => 1,
            'active' => true,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'Bronze'],
            ],
        ]], $context);

        $this->repository->delete([['id' => $id]], $context);

        $criteria = new Criteria([$id]);

        static::assertCount(0, $this->repository->search($criteria, $context)->getEntities());
    }
}

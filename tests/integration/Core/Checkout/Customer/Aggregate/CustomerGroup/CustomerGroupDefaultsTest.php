<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Customer\Aggregate\CustomerGroup;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
class CustomerGroupDefaultsTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testACreateWithoutTheTaxDisplayAndPriceBasisGetsTheDefaultPairing(): void
    {
        $customerGroup = $this->createGroup([]);

        static::assertTrue($customerGroup->getDisplayGross());
        static::assertSame(CustomerGroupEntity::PRICE_BASIS_GROSS, $customerGroup->getPriceBasis());
    }

    public function testANetDisplayCreateWithoutABasisDerivesTheNetBasisBeforeTheMajor(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $customerGroup = $this->createGroup(['displayGross' => false]);

        static::assertSame(CustomerGroupEntity::PRICE_BASIS_NET, $customerGroup->getPriceBasis());
    }

    public function testANetDisplayCreateWithoutABasisGetsTheGrossDefaultWithTheMajor(): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $customerGroup = $this->createGroup(['displayGross' => false]);

        static::assertSame(CustomerGroupEntity::PRICE_BASIS_GROSS, $customerGroup->getPriceBasis());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createGroup(array $payload): CustomerGroupEntity
    {
        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();

        /** @var EntityRepository<CustomerGroupCollection> $repository */
        $repository = static::getContainer()->get('customer_group.repository');
        $repository->create([['id' => $id, 'name' => 'field unaware group', ...$payload]], $context);

        $customerGroup = $repository->search(new Criteria([$id]), $context)->getEntities()->first();
        static::assertNotNull($customerGroup);

        return $customerGroup;
    }
}

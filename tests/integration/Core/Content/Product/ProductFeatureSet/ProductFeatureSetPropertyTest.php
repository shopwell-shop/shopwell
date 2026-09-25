<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Product\ProductFeatureSet;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\ProductEntity;
use Shopwell\Core\Content\Test\Product\ProductFeatureSet\ProductFeatureSetFixtures;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Package('inventory')]
class ProductFeatureSetPropertyTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ProductFeatureSetFixtures;

    #[TestWith(['featureSet'])]
    public function testDefinitionFieldsAreComplete(string $field): void
    {
        $definition = static::getContainer()->get(ProductDefinition::class);

        static::assertTrue($definition->getFields()->has($field));
    }

    #[TestWith(['getFeatureSet'])]
    public function testEntityIsComplete(string $method): void
    {
        static::assertTrue(method_exists(ProductEntity::class, $method));
    }

    #[TestWith(['FeatureSetBasic'])]
    #[TestWith(['FeatureSetComplete'])]
    public function testFeatureSetsCanBeCreated(string $type): void
    {
        $this->getFeatureSetFixture($type);
    }
}

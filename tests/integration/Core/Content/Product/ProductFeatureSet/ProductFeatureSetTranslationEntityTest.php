<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Product\ProductFeatureSet;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\Aggregate\ProductFeatureSetTranslation\ProductFeatureSetTranslationCollection;
use Shopwell\Core\Content\Product\Aggregate\ProductFeatureSetTranslation\ProductFeatureSetTranslationDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductFeatureSetTranslation\ProductFeatureSetTranslationEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('inventory')]
class ProductFeatureSetTranslationEntityTest extends TestCase
{
    use KernelTestBehaviour;

    private ProductFeatureSetTranslationDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->definition = static::getContainer()->get(ProductFeatureSetTranslationDefinition::class);
    }

    public function testEntityDefinitionIsComplete(): void
    {
        static::assertSame(ProductFeatureSetTranslationDefinition::ENTITY_NAME, $this->definition->getEntityName());
        static::assertSame(ProductFeatureSetTranslationCollection::class, $this->definition->getCollectionClass());
        static::assertSame(ProductFeatureSetTranslationEntity::class, $this->definition->getEntityClass());
    }

    #[TestWith(['name'])]
    #[TestWith(['description'])]
    public function testDefinitionFieldsAreComplete(string $field): void
    {
        static::assertTrue($this->definition->getFields()->has($field));
    }

    #[TestWith(['getProductFeatureSetId'])]
    #[TestWith(['getName'])]
    #[TestWith(['getDescription'])]
    #[TestWith(['getProductFeatureSet'])]
    public function testEntityIsComplete(string $method): void
    {
        static::assertTrue(method_exists(ProductFeatureSetTranslationEntity::class, $method));
    }

    public function testRepositoryIsWorking(): void
    {
        static::assertInstanceOf(EntityRepository::class, static::getContainer()->get('product_feature_set_translation.repository'));
    }
}

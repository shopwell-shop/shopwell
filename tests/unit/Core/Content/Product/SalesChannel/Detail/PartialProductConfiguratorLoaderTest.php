<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\SalesChannel\Detail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingCollection;
use Shopwell\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingEntity;
use Shopwell\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;
use Shopwell\Core\Content\Product\SalesChannel\Detail\AbstractAvailableCombinationLoader;
use Shopwell\Core\Content\Product\SalesChannel\Detail\AvailableCombinationResult;
use Shopwell\Core\Content\Product\SalesChannel\Detail\PartialProductConfiguratorLoader;
use Shopwell\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopwell\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorProductData;
use Shopwell\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopwell\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopwell\Core\Content\Property\PropertyGroupDefinition;
use Shopwell\Core\Content\Property\PropertyGroupEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(PartialProductConfiguratorLoader::class)]
class PartialProductConfiguratorLoaderTest extends TestCase
{
    public function testLoadsConfiguratorWithOnlyTheRequiredProductData(): void
    {
        $parentId = Uuid::randomHex();
        $groupId = Uuid::randomHex();
        $optionId = Uuid::randomHex();

        $combinationResult = new AvailableCombinationResult();
        $combinationResult->addCombination([$optionId], true);

        $combinationLoader = static::createStub(AbstractAvailableCombinationLoader::class);
        $combinationLoader->method('loadCombinations')->willReturn($combinationResult);

        /** @var StaticEntityRepository<PropertyGroupOptionCollection> $optionRepository */
        $optionRepository = StaticEntityRepository::of(PropertyGroupOptionCollection::class, [
            new PropertyGroupOptionCollection([$this->buildOption($optionId, $groupId)]),
        ]);
        $productConfiguratorLoader = new ProductConfiguratorLoader($combinationLoader, $optionRepository);
        $loader = new PartialProductConfiguratorLoader($productConfiguratorLoader);

        $groups = $loader->load(
            new ProductConfiguratorProductData(
                Uuid::randomHex(),
                $parentId,
                [$optionId],
                new VariantListingConfig(null, null, [['id' => $groupId]]),
            ),
            Generator::generateSalesChannelContext(),
        );

        static::assertCount(1, $groups);
        static::assertNotNull($groups->get($groupId));
    }

    private function buildOption(string $optionId, string $groupId): PropertyGroupOptionEntity
    {
        $group = new PropertyGroupEntity();
        $group->setId($groupId);
        $group->setName('Color');
        $group->setTranslated(['name' => 'Color']);
        $group->setPosition(1);
        $group->setSortingType(PropertyGroupDefinition::SORTING_TYPE_POSITION);

        $setting = new ProductConfiguratorSettingEntity();
        $setting->setId(Uuid::randomHex());
        $setting->setOptionId($optionId);
        $setting->setPosition(1);

        $option = new PropertyGroupOptionEntity();
        $option->setId($optionId);
        $option->setName('Red');
        $option->setTranslated(['name' => 'Red']);
        $option->setGroupId($groupId);
        $option->setGroup($group);
        $option->setProductConfiguratorSettings(new ProductConfiguratorSettingCollection([$setting]));

        return $option;
    }
}

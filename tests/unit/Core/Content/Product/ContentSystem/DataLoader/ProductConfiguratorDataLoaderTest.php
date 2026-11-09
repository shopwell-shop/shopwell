<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\ContentSystem\DataLoader\ProductConfiguratorDataLoader;
use Shopwell\Core\Content\Product\ContentSystem\DataLoader\ProductConfiguratorLoaderConfig;
use Shopwell\Core\Content\Product\SalesChannel\Detail\PartialProductConfiguratorLoader;
use Shopwell\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopwell\Core\Content\Property\PropertyGroupCollection;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopwell\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticSalesChannelRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductConfiguratorDataLoader::class)]
class ProductConfiguratorDataLoaderTest extends TestCase
{
    public function testReturnsNotFoundForMissingProductId(): void
    {
        /** @var StaticSalesChannelRepository<SalesChannelProductCollection> $productRepository */
        $productRepository = new StaticSalesChannelRepository([new SalesChannelProductCollection()]);
        $loader = new ProductConfiguratorDataLoader(
            new PartialProductConfiguratorLoader(static::createStub(ProductConfiguratorLoader::class)),
            $productRepository,
        );
        $requirement = new DataRequirement('configuratorSettings', ProductConfiguratorDataLoader::SOURCE, new ProductConfiguratorLoaderConfig());

        static::assertFalse($loader->load(new LoaderInputs(['productId' => null]), $requirement, Generator::generateSalesChannelContext(), new Request())->hasData());
    }

    public function testDelegatesProductIdToConfiguratorLoader(): void
    {
        $entity = new PartialEntity([
            'id' => 'product-id',
            'parentId' => 'parent-id',
        ]);
        $groups = new PropertyGroupCollection();
        $productConfiguratorLoader = $this->createMock(ProductConfiguratorLoader::class);
        $context = Generator::generateSalesChannelContext();
        $productConfiguratorLoader->expects($this->once())
            ->method('load')
            ->with(
                static::callback(static function (SalesChannelProductEntity $product): bool {
                    return $product->getId() === 'product-id'
                        && $product->getParentId() === 'parent-id'
                        && $product->getOptionIds() === null
                        && $product->getVariantListingConfig() === null;
                }),
                $context,
            )
            ->willReturn($groups);

        /** @var StaticSalesChannelRepository<SalesChannelProductCollection> $productRepository */
        $productRepository = new StaticSalesChannelRepository([[$entity]]);
        $loader = new ProductConfiguratorDataLoader(
            new PartialProductConfiguratorLoader($productConfiguratorLoader),
            $productRepository,
        );
        $requirement = new DataRequirement('configuratorSettings', ProductConfiguratorDataLoader::SOURCE, new ProductConfiguratorLoaderConfig());

        $result = $loader->load(new LoaderInputs(['productId' => 'product-id']), $requirement, $context, new Request());

        static::assertSame($groups, $result->data);
    }
}

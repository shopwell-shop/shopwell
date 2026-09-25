<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopwell\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfig;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopwell\Core\Content\Cms\SalesChannel\Struct\BuyBoxStruct;
use Shopwell\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopwell\Core\Content\Product\Cms\BuyBoxCmsElementResolver;
use Shopwell\Core\Content\Product\ProductEntity;
use Shopwell\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BuyBoxCmsElementResolver::class)]
class BuyBoxCmsElementResolverTest extends TestCase
{
    public function testGetType(): void
    {
        /** @var StaticEntityRepository<ProductReviewCollection> */
        $repository = new StaticEntityRepository([]);

        $resolver = new BuyBoxCmsElementResolver(
            static::createStub(ProductConfiguratorLoader::class),
            $repository,
        );

        static::assertSame('buy-box', $resolver->getType());
    }

    public function testEnrichBuyBox(): void
    {
        $configurationLoader = static::createStub(ProductConfiguratorLoader::class);
        /** @var EntityRepository<ProductReviewCollection>&MockObject */
        $repository = static::createStub(EntityRepository::class);
        $repository->method('aggregate')->willReturn(new AggregationResultCollection());

        $resolver = new BuyBoxCmsElementResolver($configurationLoader, $repository);

        $productId = 'product-1';
        $config = new FieldConfigCollection([new FieldConfig('product', FieldConfig::SOURCE_STATIC, $productId)]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());

        $product = new SalesChannelProductEntity();
        $product->setId($productId);

        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new SalesChannelProductCollection([$product]));

        $data = new ElementDataCollection();
        $data->add('product_slot-1', $result);

        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(BuyBoxStruct::class, $data);

        $product = $data->getProduct();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertSame('product-1', $product->getId());
    }

    public function testEnrichSetsEmptyBuyBoxWithoutConfig(): void
    {
        $configurationLoader = static::createStub(ProductConfiguratorLoader::class);

        /** @var StaticEntityRepository<ProductReviewCollection> */
        $repository = new StaticEntityRepository([]);

        $resolver = new BuyBoxCmsElementResolver($configurationLoader, $repository);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');

        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());
        $data = new ElementDataCollection();

        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(BuyBoxStruct::class, $data);
        static::assertNull($data->getProduct());
    }
}

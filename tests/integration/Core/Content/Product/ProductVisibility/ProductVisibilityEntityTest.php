<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Product\ProductVisibility;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityCollection;
use Shopwell\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityEntity;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\ProductEntity;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\Util\AccessKeyHelper;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('inventory')]
class ProductVisibilityEntityTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<ProductCollection>
     */
    protected EntityRepository $productRepository;

    private string $salesChannelId1;

    private string $salesChannelId2;

    /**
     * @var EntityRepository<ProductVisibilityCollection>
     */
    private EntityRepository $visibilityRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->visibilityRepository = static::getContainer()->get('product_visibility.repository');

        $this->salesChannelId1 = Uuid::randomHex();
        $this->salesChannelId2 = Uuid::randomHex();

        $this->createSalesChannel($this->salesChannelId1);
        $this->createSalesChannel($this->salesChannelId2);
    }

    public function testVisibilityCRUD(): void
    {
        $id = Uuid::randomHex();

        $product = $this->createProduct(
            $id,
            [
                $this->salesChannelId1 => ProductVisibilityDefinition::VISIBILITY_SEARCH,
                $this->salesChannelId2 => ProductVisibilityDefinition::VISIBILITY_LINK,
            ]
        );

        $context = Context::createDefaultContext();

        $container = $this->productRepository->create([$product], $context);

        $event = $container->getEventByEntityName(ProductVisibilityDefinition::ENTITY_NAME);

        // visibility created?
        static::assertInstanceOf(EntityWrittenEvent::class, $event);
        static::assertCount(2, $event->getWriteResults());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('visibilities');

        $product = $this->productRepository->search($criteria, $context)->getEntities()->first();

        // check visibilities can be loaded as association
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(ProductVisibilityCollection::class, $product->getVisibilities());
        static::assertCount(2, $product->getVisibilities());

        // check read for visibilities
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product_visibility.productId', $id));

        $visibilities = $this->visibilityRepository->search($criteria, $context)->getEntities();
        static::assertCount(2, $visibilities);

        // test filter visibilities over product
        $criteria = new Criteria([$id]);

        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new RangeFilter('product.visibilities.visibility', [
                        RangeFilter::GTE => ProductVisibilityDefinition::VISIBILITY_LINK,
                    ]),
                    new EqualsFilter('product.visibilities.salesChannelId', $this->salesChannelId1),
                ]
            )
        );

        $product = $this->productRepository->search($criteria, $context)->getEntities()->first();

        // visibilities filtered and loaded?
        static::assertInstanceOf(ProductEntity::class, $product);

        $ids = $visibilities->map(
            static fn (ProductVisibilityEntity $visibility) => ['id' => $visibility->getId()]
        );

        $container = $this->visibilityRepository->delete(array_values($ids), $context);

        $event = $container->getEventByEntityName(ProductVisibilityDefinition::ENTITY_NAME);
        static::assertInstanceOf(EntityWrittenEvent::class, $event);
        static::assertCount(2, $event->getWriteResults());
    }

    /**
     * @param array<string, int> $visibilities
     *
     * @return array<string, mixed>
     */
    private function createProduct(string $id, array $visibilities): array
    {
        $mapped = [];
        foreach ($visibilities as $salesChannel => $visibility) {
            $mapped[] = ['salesChannelId' => $salesChannel, 'visibility' => $visibility];
        }

        return [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'visibilities' => $mapped,
        ];
    }

    private function createSalesChannel(string $id): void
    {
        $data = [
            'id' => $id,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_API,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $this->getValidCountryId(),
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
            'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
            'countries' => [['id' => $this->getValidCountryId()]],
            'name' => 'first sales-channel',
            'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
        ];

        static::getContainer()->get('sales_channel.repository')->create([$data], Context::createDefaultContext());
    }
}

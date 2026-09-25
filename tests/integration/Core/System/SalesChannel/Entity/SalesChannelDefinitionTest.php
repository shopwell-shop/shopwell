<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\SalesChannel\Entity;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Category\SalesChannel\SalesChannelCategoryDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopwell\Core\System\SalesChannel\SalesChannelCollection;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('discovery')]
class SalesChannelDefinitionTest extends TestCase
{
    use IntegrationTestBehaviour;

    private SalesChannelDefinitionInstanceRegistry $registry;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $apiRepository;

    /**
     * @var SalesChannelRepository<SalesChannelProductCollection>
     */
    private SalesChannelRepository $salesChannelProductRepository;

    private AbstractSalesChannelContextFactory $factory;

    protected function setUp(): void
    {
        $this->registry = static::getContainer()->get(SalesChannelDefinitionInstanceRegistry::class);
        $this->apiRepository = static::getContainer()->get('product.repository');
        $this->salesChannelProductRepository = static::getContainer()->get('sales_channel.product.repository');
        $this->factory = static::getContainer()->get(SalesChannelContextFactory::class);
    }

    public function testAssociationReplacement(): void
    {
        $fields = static::getContainer()->get(SalesChannelProductDefinition::class)->getFields();

        $categories = $fields->get('categories');

        /** @var ManyToManyAssociationField $categories */
        static::assertSame(
            static::getContainer()->get(SalesChannelCategoryDefinition::class)->getClass(),
            $categories->getToManyReferenceDefinition()->getClass()
        );

        static::assertSame(
            static::getContainer()->get(SalesChannelCategoryDefinition::class),
            $categories->getToManyReferenceDefinition()
        );

        $fields = static::getContainer()->get(ProductDefinition::class)->getFields();
        $categories = $fields->get('categories');

        /** @var ManyToManyAssociationField $categories */
        static::assertSame(
            static::getContainer()->get(CategoryDefinition::class),
            $categories->getToManyReferenceDefinition()
        );
    }

    public function testDefinitionRegistry(): void
    {
        static::assertSame(
            static::getContainer()->get(SalesChannelProductDefinition::class),
            $this->registry->getByEntityName('product')
        );
    }

    public function testRepositoryCompilerPass(): void
    {
        static::assertInstanceOf(
            SalesChannelRepository::class,
            static::getContainer()->get('sales_channel.product.repository')
        );
    }

    public function testLoadEntities(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => 'test',
            'stock' => 10,
            'active' => true,
            'name' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $id, 'name' => 'asd'],
            ],
            'visibilities' => [
                [
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        $this->apiRepository->create([$data], Context::createDefaultContext());

        $dispatcher = static::getContainer()->get('event_dispatcher');
        $listener = $this->createMock(CallableClass::class);
        $listener->expects($this->once())->method('__invoke');
        $this->addEventListener($dispatcher, 'sales_channel.product.loaded', $listener);

        $context = $this->factory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('categories');

        $products = $this->salesChannelProductRepository->search($criteria, $context)->getEntities();

        static::assertCount(1, $products);

        /** @var SalesChannelProductEntity $product */
        $product = $products->first();
        static::assertInstanceOf(SalesChannelProductEntity::class, $product);

        $categories = $product->getCategories();

        static::assertNotNull($categories);
        static::assertCount(1, $categories);
    }

    public function testBusinessTimeZoneCanBeWrittenAndCleared(): void
    {
        $repository = static::getContainer()->get('sales_channel.repository');
        $context = Context::createDefaultContext();
        $originalTimeZone = $this->getBusinessTimeZone($repository, $context);

        try {
            $repository->update([[
                'id' => TestDefaults::SALES_CHANNEL,
                'businessTimeZone' => 'Europe/Berlin',
            ]], $context);

            static::assertSame('Europe/Berlin', $this->getBusinessTimeZone($repository, $context));

            $repository->update([[
                'id' => TestDefaults::SALES_CHANNEL,
                'businessTimeZone' => null,
            ]], $context);

            static::assertNull($this->getBusinessTimeZone($repository, $context));
        } finally {
            $repository->update([[
                'id' => TestDefaults::SALES_CHANNEL,
                'businessTimeZone' => $originalTimeZone,
            ]], $context);
        }
    }

    /**
     * @param EntityRepository<SalesChannelCollection> $repository
     */
    private function getBusinessTimeZone(EntityRepository $repository, Context $context): ?string
    {
        $salesChannel = $repository->search(new Criteria([TestDefaults::SALES_CHANNEL]), $context)->getEntities()->first();
        static::assertInstanceOf(SalesChannelEntity::class, $salesChannel);

        return $salesChannel->getBusinessTimeZone();
    }
}

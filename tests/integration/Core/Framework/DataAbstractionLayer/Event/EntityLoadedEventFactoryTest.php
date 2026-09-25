<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer\Event;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Test\Product\ProductBuilder;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Event\NestedEventCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\System\Language\LanguageCollection;
use Shopwell\Core\System\Language\LanguageEntity;
use Shopwell\Core\System\Tax\TaxEntity;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
class EntityLoadedEventFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    private IdsCollection $ids;

    private EntityLoadedEventFactory $entityLoadedEventFactory;

    protected function setUp(): void
    {
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->entityLoadedEventFactory = static::getContainer()->get(EntityLoadedEventFactory::class);
        $this->ids = new IdsCollection();
    }

    public function testCreate(): void
    {
        $builder = (new ProductBuilder($this->ids, 'p1'))
            ->price(10)
            ->category('c1')
            ->manufacturer('m1')
            ->prices('r1', 5);

        $this->productRepository->create([$builder->build()], Context::createDefaultContext());

        $criteria = new Criteria([$this->ids->get('p1')]);
        $criteria->addAssociations([
            'manufacturer',
            'prices',
            'categories',
        ]);

        $product = $this->productRepository->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();
        static::assertNotNull($product);

        $product->addExtension('test', new LanguageCollection([
            (new LanguageEntity())->assign(['id' => $this->ids->create('l1'), '_entityName' => 'language']),
        ]));

        $events = $this->entityLoadedEventFactory->create([$product], Context::createDefaultContext())->getEvents();
        static::assertNotNull($events);
        static::assertContainsOnlyInstancesOf(EntityLoadedEvent::class, $events);
        /** @var NestedEventCollection<EntityLoadedEvent<Entity>> $events */
        $createdEvents = $events->map(static fn (EntityLoadedEvent $event): string => $event->getName());
        sort($createdEvents);

        static::assertSame([
            'category.loaded',
            'language.loaded',
            'product.loaded',
            'product_manufacturer.loaded',
            'product_price.loaded',
        ], $createdEvents);
    }

    public function testCollectionWithEntitiesMixed(): void
    {
        $tax = (new TaxEntity())->assign(['_entityName' => 'tax']);

        $events = $this->entityLoadedEventFactory->create([new ProductCollection(), $tax], Context::createDefaultContext())->getEvents();
        static::assertNotNull($events);
        static::assertContainsOnlyInstancesOf(EntityLoadedEvent::class, $events);
        /** @var NestedEventCollection<EntityLoadedEvent<Entity>> $events */
        $createdEvents = $events->map(static fn (EntityLoadedEvent $event): string => $event->getName());
        sort($createdEvents);

        static::assertSame([
            'tax.loaded',
        ], $createdEvents);
    }
}

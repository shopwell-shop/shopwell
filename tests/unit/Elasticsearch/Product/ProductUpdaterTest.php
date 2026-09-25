<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Elasticsearch\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\Events\ProductIndexerEvent;
use Shopwell\Core\Content\Product\Events\ProductStockAlteredEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopwell\Elasticsearch\Product\ProductUpdater;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ProductUpdater::class)]
class ProductUpdaterTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            ProductIndexerEvent::class => 'update',
            ProductStockAlteredEvent::class => 'update',
        ], ProductUpdater::getSubscribedEvents());
    }

    public function testUpdate(): void
    {
        $indexer = $this->createMock(ElasticsearchIndexer::class);
        $definition = static::createStub(EntityDefinition::class);

        $indexer->expects($this->once())->method('updateIds')->with($definition, ['id1', 'id2']);

        $event = new ProductIndexerEvent(['id1', 'id2'], Context::createDefaultContext());

        $updater = new ProductUpdater($indexer, $definition);
        $updater->update($event);
    }

    public function testStockUpdate(): void
    {
        $indexer = $this->createMock(ElasticsearchIndexer::class);
        $definition = static::createStub(EntityDefinition::class);

        $indexer->expects($this->once())->method('updateIds')->with($definition, ['id1', 'id2']);

        $event = new ProductStockAlteredEvent(['id1', 'id2'], Context::createDefaultContext());

        $updater = new ProductUpdater($indexer, $definition);
        $updater->update($event);
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Elasticsearch\Admin\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopwell\Core\Content\Product\Events\ProductIndexerEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Elasticsearch\Admin\AdminSearchRegistry;
use Shopwell\Elasticsearch\Admin\Subscriber\ProductInheritanceSubscriber;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductInheritanceSubscriber::class)]
class ProductInheritanceSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame([ProductIndexerEvent::class => 'update'], ProductInheritanceSubscriber::getSubscribedEvents());
    }

    public function testProductsWhoseInheritanceWasUpdatedAreIndexed(): void
    {
        $context = Context::createDefaultContext();
        $registry = $this->createMock(AdminSearchRegistry::class);
        $registry->expects($this->once())->method('updateIds')->with('product', ['variant-1', 'variant-2'], $context);

        Feature::fake(['ENABLE_OPENSEARCH_FOR_ADMIN_API'], static function () use ($registry, $context): void {
            (new ProductInheritanceSubscriber($registry))->update(new ProductIndexerEvent(['variant-1', 'variant-2'], $context, [ProductIndexer::STOCK_UPDATER]));
        });
    }

    public function testNothingIsIndexedWithoutTheAdvancedAdminSearch(): void
    {
        $registry = $this->createMock(AdminSearchRegistry::class);
        $registry->expects($this->never())->method('updateIds');

        Feature::fake([], static function () use ($registry): void {
            $_SERVER['ENABLE_OPENSEARCH_FOR_ADMIN_API'] = false;

            (new ProductInheritanceSubscriber($registry))->update(new ProductIndexerEvent(['variant-1'], Context::createDefaultContext(), []));
        });
    }

    public function testProductsOfAFullIndexingRunAreLeftToTheAdminIndexRebuild(): void
    {
        $registry = $this->createMock(AdminSearchRegistry::class);
        $registry->expects($this->never())->method('updateIds');

        Feature::fake(['ENABLE_OPENSEARCH_FOR_ADMIN_API'], static function () use ($registry): void {
            (new ProductInheritanceSubscriber($registry))->update(new ProductIndexerEvent(['product-1'], Context::createDefaultContext(), [], true));
        });
    }

    public function testProductsWhoseInheritanceWasSkippedAreNotIndexedAgain(): void
    {
        $registry = $this->createMock(AdminSearchRegistry::class);
        $registry->expects($this->never())->method('updateIds');

        Feature::fake(['ENABLE_OPENSEARCH_FOR_ADMIN_API'], static function () use ($registry): void {
            (new ProductInheritanceSubscriber($registry))->update(new ProductIndexerEvent(['product-1'], Context::createDefaultContext(), [ProductIndexer::INHERITANCE_UPDATER]));
        });
    }
}

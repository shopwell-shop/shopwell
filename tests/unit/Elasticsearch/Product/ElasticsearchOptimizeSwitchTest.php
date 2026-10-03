<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Elasticsearch\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Annotation\DisabledFeatures;
use Shopwell\Core\Test\Stub\Framework\Adapter\Storage\ArrayKeyValueStorage;
use Shopwell\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexingFinishedEvent;
use Shopwell\Elasticsearch\Product\ElasticsearchOptimizeSwitch;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ElasticsearchOptimizeSwitch::class)]
#[DisabledFeatures(['v6.8.0.0'])]
class ElasticsearchOptimizeSwitchTest extends TestCase
{
    public function testGetSubscribers(): void
    {
        $subscribers = ElasticsearchOptimizeSwitch::getSubscribedEvents();

        static::assertSame([
            ElasticsearchIndexingFinishedEvent::class => 'onIndexingFinished',
        ], $subscribers);
    }

    public function testOnIndexingFinished(): void
    {
        $storage = new ArrayKeyValueStorage([]);

        static::assertFalse($storage->has(ElasticsearchOptimizeSwitch::FLAG));

        $event = new ElasticsearchIndexingFinishedEvent();
        $subscriber = new ElasticsearchOptimizeSwitch($storage);
        $subscriber->onIndexingFinished($event);

        static::assertTrue($storage->get(ElasticsearchOptimizeSwitch::FLAG));
    }
}

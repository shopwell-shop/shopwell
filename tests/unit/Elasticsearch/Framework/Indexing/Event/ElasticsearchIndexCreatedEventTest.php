<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Elasticsearch\Framework\Indexing\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopwell\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexCreatedEvent;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElasticsearchIndexCreatedEvent::class)]
class ElasticsearchIndexCreatedEventTest extends TestCase
{
    public function testEvent(): void
    {
        $event = new ElasticsearchIndexCreatedEvent('index', static::createStub(AbstractElasticsearchDefinition::class));
        static::assertSame('index', $event->getIndexName());
    }
}

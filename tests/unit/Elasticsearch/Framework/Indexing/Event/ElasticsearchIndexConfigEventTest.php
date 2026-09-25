<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Elasticsearch\Framework\Indexing\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopwell\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexConfigEvent;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElasticsearchIndexConfigEvent::class)]
class ElasticsearchIndexConfigEventTest extends TestCase
{
    public function testEvent(): void
    {
        $event = new ElasticsearchIndexConfigEvent('index', ['config' => 'value'], static::createStub(AbstractElasticsearchDefinition::class), Context::createDefaultContext());
        static::assertSame('index', $event->getIndexName());
        static::assertSame(['config' => 'value'], $event->getConfig());

        $event->setConfig(['config' => 'value2']);

        static::assertSame(['config' => 'value2'], $event->getConfig());
    }
}

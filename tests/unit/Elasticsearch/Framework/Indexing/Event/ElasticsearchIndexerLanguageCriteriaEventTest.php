<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Elasticsearch\Framework\Indexing\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexerLanguageCriteriaEvent;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElasticsearchIndexerLanguageCriteriaEvent::class)]
class ElasticsearchIndexerLanguageCriteriaEventTest extends TestCase
{
    public function testEvent(): void
    {
        $criteria = new Criteria();
        $context = Context::createDefaultContext();

        $event = new ElasticsearchIndexerLanguageCriteriaEvent($criteria, $context);
        static::assertSame($criteria, $event->getCriteria());
        static::assertSame($context, $event->getContext());
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Flow\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(BeforeLoadStorableFlowDataEvent::class)]
class BeforeLoadStorableFlowDataEventTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetters(): void
    {
        $criteria = new Criteria();
        $context = Context::createDefaultContext();
        $event = new BeforeLoadStorableFlowDataEvent(
            'entity_name',
            $criteria,
            $context
        );

        static::assertSame('entity_name', $event->getEntityName());
        static::assertSame('flow.storer.entity_name.criteria.event', $event->getName());
        static::assertSame($criteria, $event->getCriteria());
        static::assertSame($context, $event->getContext());
    }
}

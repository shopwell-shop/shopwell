<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Telemetry\EntityTelemetrySubscriber;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Telemetry\Metrics\Meter;
use Shopwell\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityTelemetrySubscriber::class)]
class EntityTelemetrySubscriberTest extends TestCase
{
    public function testEmitAssociationsCountMetric(): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('association1');
        $criteria->addAssociation('association2');

        $event = new EntitySearchedEvent($criteria, static::createStub(EntityDefinition::class), Context::createDefaultContext());
        $meter = $this->createMock(Meter::class);
        $meter->expects($this->once())
            ->method('emit')
            ->with(static::callback(static function (ConfiguredMetric $metric) {
                return $metric->name === 'dal.associations.count' && $metric->value === 2;
            }));

        $subscriber = new EntityTelemetrySubscriber($meter);
        $subscriber->emitAssociationsCountMetric($event);
    }
}

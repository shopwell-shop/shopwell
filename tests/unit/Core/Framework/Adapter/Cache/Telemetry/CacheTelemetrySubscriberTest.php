<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Adapter\Cache\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Cache\Telemetry\CacheTelemetrySubscriber;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Telemetry\Metrics\Meter;
use Shopwell\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CacheTelemetrySubscriber::class)]
class CacheTelemetrySubscriberTest extends TestCase
{
    public function testEmitInvalidateCacheCountMetric(): void
    {
        $meter = $this->createMock(Meter::class);
        $meter->expects($this->once())
            ->method('emit')
            ->with(static::callback(static function (ConfiguredMetric $metric) {
                return $metric->name === 'cache.invalidate.count' && $metric->value === 1;
            }));

        $subscriber = new CacheTelemetrySubscriber($meter);
        $subscriber->emitInvalidateCacheCountMetric();
    }
}

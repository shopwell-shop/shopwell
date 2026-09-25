<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\App\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\Telemetry\AppTelemetrySubscriber;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Telemetry\Metrics\Meter;
use Shopwell\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppTelemetrySubscriber::class)]
class AppTelemetrySubscriberTest extends TestCase
{
    public function testEmitAppInstalledMetric(): void
    {
        $meter = $this->createMock(Meter::class);
        $meter->expects($this->once())
            ->method('emit')
            ->with(static::callback(static function (ConfiguredMetric $metric) {
                return $metric->name === 'app.install.count' && $metric->value === 1;
            }));

        $subscriber = new AppTelemetrySubscriber($meter);
        $subscriber->emitAppInstalledMetric();
    }
}

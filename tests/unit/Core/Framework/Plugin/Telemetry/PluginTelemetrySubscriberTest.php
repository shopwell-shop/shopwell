<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Plugin\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Telemetry\PluginTelemetrySubscriber;
use Shopwell\Core\Framework\Telemetry\Metrics\Meter;
use Shopwell\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PluginTelemetrySubscriber::class)]
class PluginTelemetrySubscriberTest extends TestCase
{
    public function testEmitPluginInstallCountMetric(): void
    {
        $meter = $this->createMock(Meter::class);
        $meter->expects($this->once())
            ->method('emit')
            ->with(static::callback(static function (ConfiguredMetric $metric) {
                return $metric->name === 'plugin.install.count' && $metric->value === 1;
            }));

        $subscriber = new PluginTelemetrySubscriber($meter);
        $subscriber->emitPluginInstallCountMetric();
    }
}

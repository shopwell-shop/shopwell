<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Telemetry\Metrics\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Telemetry\Metrics\Config\MetricConfig;
use Shopwell\Core\Framework\Telemetry\Metrics\Exception\MetricNotSupportedException;
use Shopwell\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopwell\Core\Framework\Telemetry\Metrics\Metric\Metric;
use Shopwell\Core\Framework\Telemetry\Metrics\Metric\Type;
use Shopwell\Core\Framework\Telemetry\Metrics\MetricTransportInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MetricNotSupportedException::class)]
class MetricNotSupportedExceptionTest extends TestCase
{
    public function testGetErrorCode(): void
    {
        $transport = static::createStub(MetricTransportInterface::class);
        $metricConfig = new MetricConfig('test', description: 'test', type: Type::COUNTER, enabled: true, parameters: []);
        $metric = Metric::fromConfigured(new ConfiguredMetric('test', 1, []), $metricConfig, []);
        $exception = new MetricNotSupportedException($metric, $transport);
        static::assertSame('TELEMETRY__METRIC_NOT_SUPPORTED', $exception->getErrorCode());
    }
}

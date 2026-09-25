<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Test\Telemetry\Factory;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Telemetry\Metrics\Config\TransportConfig;
use Shopwell\Core\Framework\Telemetry\Metrics\Factory\MetricTransportFactoryInterface;
use Shopwell\Core\Framework\Telemetry\Metrics\MetricTransportInterface;
use Shopwell\Core\Framework\Test\Telemetry\Transport\TraceableTransport;

/**
 * @internal
 */
#[Package('framework')]
class TraceableTransportFactory implements MetricTransportFactoryInterface
{
    public function create(TransportConfig $transportConfig): MetricTransportInterface
    {
        return new TraceableTransport();
    }
}

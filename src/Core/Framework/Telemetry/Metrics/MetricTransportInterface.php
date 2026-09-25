<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Telemetry\Metrics;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Telemetry\Metrics\Exception\MetricNotSupportedException;
use Shopwell\Core\Framework\Telemetry\Metrics\Metric\Metric;

/**
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
interface MetricTransportInterface
{
    /**
     * @throws MetricNotSupportedException
     */
    public function emit(Metric $metric): void;

    /**
     * Called by the framework on `kernel.terminate` and `console.terminate`.
     * Push transports can use this to flush batched emissions; pull transports
     * can persist aggregated values. Implement as a no-op when not needed.
     */
    public function flush(): void;
}

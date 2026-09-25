<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Telemetry\Metrics\Metric;

use Shopwell\Core\Framework\Log\Package;

/**
 * @phpstan-type MetricTypeValues = 'histogram'|'gauge'|'counter'|'updown_counter'
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum Type: string
{
    case HISTOGRAM = 'histogram';

    case GAUGE = 'gauge';

    case COUNTER = 'counter';

    case UPDOWN_COUNTER = 'updown_counter';
}

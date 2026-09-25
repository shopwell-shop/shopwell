<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Telemetry\Instrumentation;

use Shopwell\Core\Framework\Log\Package;

/**
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 *
 * @codeCoverageIgnore - value object
 */
#[Package('framework')]
final readonly class DurationMetric
{
    /**
     * @param array<non-empty-string, string|bool|float|int> $labels
     */
    public function __construct(
        public string $name,
        public array $labels = [],
    ) {
    }
}

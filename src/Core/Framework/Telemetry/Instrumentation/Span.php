<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Telemetry\Instrumentation;

use Shopwell\Core\Framework\Log\Package;

/**
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 *
 * @codeCoverageIgnore - value object
 */
#[Package('framework')]
final readonly class Span
{
    /**
     * @param array<string> $tags
     */
    public function __construct(
        public string $name,
        public string $category = 'shopwell',
        public array $tags = [],
    ) {
    }
}

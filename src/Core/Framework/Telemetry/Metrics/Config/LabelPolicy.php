<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Telemetry\Metrics\Config;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
enum LabelPolicy: string
{
    case REPLACE = 'replace';

    case DISCARD = 'discard';

    case OPEN = 'open';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}

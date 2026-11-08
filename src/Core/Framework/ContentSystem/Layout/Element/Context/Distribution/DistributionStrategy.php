<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Layout\Element\Context\Distribution;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
enum DistributionStrategy: string
{
    case Broadcast = 'broadcast';
    case Indexed = 'indexed';
    case Keyed = 'keyed';
    case Sliced = 'sliced';
    case Iterator = 'iterator';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

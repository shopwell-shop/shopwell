<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Hydration\DataContext;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
enum ContextType: string
{
    case Single = 'single';
    case Collection = 'collection';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

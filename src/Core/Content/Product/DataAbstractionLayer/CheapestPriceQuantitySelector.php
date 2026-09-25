<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * Allows project overrides to change cheapest price selection
 */
#[Package('framework')]
class CheapestPriceQuantitySelector extends AbstractCheapestPriceQuantitySelector
{
    public function getDecorated(): AbstractCheapestPriceQuantitySelector
    {
        throw new DecorationPatternException(self::class);
    }

    public function add(QueryBuilder $query): void
    {
        $query->addSelect(
            'price.quantity_start != 1 as is_ranged',
        );

        $query->andWhere('price.quantity_end IS NULL');
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\System\Salutation;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('checkout')]
class SalutationSorter extends AbstractSalutationsSorter
{
    public function getDecorated(): AbstractSalutationsSorter
    {
        throw new DecorationPatternException(self::class);
    }

    public function sort(SalutationCollection $salutations): SalutationCollection
    {
        $salutations->sortByPosition();

        return $salutations;
    }
}

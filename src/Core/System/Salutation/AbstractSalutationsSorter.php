<?php declare(strict_types=1);

namespace Shopwell\Core\System\Salutation;

use Shopwell\Core\Framework\Log\Package;

#[Package('checkout')]
abstract class AbstractSalutationsSorter
{
    abstract public function getDecorated(): AbstractSalutationsSorter;

    abstract public function sort(SalutationCollection $salutations): SalutationCollection;
}

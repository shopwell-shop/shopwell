<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting;

use Shopwell\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
class CountSorting extends FieldSorting
{
    protected string $type = 'count';

    public function getApiAlias(): string
    {
        return 'dal_count_sorting';
    }
}

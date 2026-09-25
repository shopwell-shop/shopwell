<?php declare(strict_types=1);

namespace Shopwell\Core\System\Tag\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@framework')]
class FilteredTagIdsStruct extends Struct
{
    /**
     * @param array<string> $ids
     */
    public function __construct(
        protected array $ids,
        protected int $total
    ) {
    }

    /**
     * @return array<string>
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    public function getTotal(): int
    {
        return $this->total;
    }
}

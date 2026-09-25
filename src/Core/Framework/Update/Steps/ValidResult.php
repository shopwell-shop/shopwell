<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Update\Steps;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class ValidResult
{
    public function __construct(
        private readonly int $offset,
        private readonly int $total
    ) {
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getTotal(): int
    {
        return $this->total;
    }
}

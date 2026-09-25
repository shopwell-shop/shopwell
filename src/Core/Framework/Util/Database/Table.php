<?php

declare(strict_types=1);

namespace Shopwell\Core\Framework\Util\Database;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class Table
{
    /**
     * @param list<Column> $columns
     * @param list<Index> $indexes
     */
    public function __construct(
        public array $columns,
        public array $indexes,
    ) {
    }
}

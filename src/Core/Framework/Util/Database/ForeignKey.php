<?php

declare(strict_types=1);

namespace Shopwell\Core\Framework\Util\Database;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ForeignKey
{
    /**
     * @param list<string> $referencingColumnNames
     * @param list<string> $referencedColumnNames
     */
    public function __construct(
        public array $referencingColumnNames,
        public string $referencedTableName,
        public array $referencedColumnNames,
        public string $onDeleteAction,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Shopwell\Core\Framework\Util\Database;

use Doctrine\DBAL\Schema\Index as DbalIndex;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class Index
{
    public function __construct(
        public string $name,
        public string $type,
    ) {
    }

    public static function createFromDbalIndex(DbalIndex $dbalIndex): self
    {
        return new Index(
            name: $dbalIndex->getObjectName()->getIdentifier()->getValue(),
            type: $dbalIndex->getType()->name,
        );
    }
}

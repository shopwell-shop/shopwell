<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\Sync;

use Shopwell\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
class FkReference
{
    public ?string $resolved = null;

    /**
     * @internal
     */
    public function __construct(
        public readonly string $pointer,
        public readonly string $entityName,
        public readonly string $fieldName,
        public mixed $value,
        public readonly bool $nullOnMissing
    ) {
    }
}

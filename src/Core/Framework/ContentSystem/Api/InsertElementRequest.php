<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Api;

use Shopwell\Core\Framework\Log\Package;

/**
 * Envelope DTO for the insert-element mutation action.
 *
 * @internal
 */
#[Package('framework')]
final class InsertElementRequest
{
    /**
     * @param array<int|string, mixed> $layout
     */
    public function __construct(
        public readonly string $type,
        public readonly array $layout = [],
        public readonly ?string $parentElementId = null,
        public readonly ?string $slot = null,
        public readonly ?int $index = null,
        public readonly ?string $rootSource = null,
        public readonly ?string $bindingSpecificationId = null,
    ) {
    }
}

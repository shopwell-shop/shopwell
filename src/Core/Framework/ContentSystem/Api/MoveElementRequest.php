<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Api;

use Shopwell\Core\Framework\Log\Package;

/**
 * Envelope DTO for the move-element mutation action.
 *
 * @internal
 */
#[Package('framework')]
final class MoveElementRequest
{
    /**
     * @param array<int|string, mixed> $layout
     */
    public function __construct(
        public readonly string $elementId,
        public readonly array $layout = [],
        public readonly ?string $newParentId = null,
        public readonly ?string $newSlot = null,
        public readonly ?int $index = null,
        public readonly ?string $rootSource = null,
    ) {
    }
}

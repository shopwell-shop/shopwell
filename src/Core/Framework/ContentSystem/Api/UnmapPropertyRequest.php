<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Api;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class UnmapPropertyRequest
{
    /**
     * @param array<int|string, mixed> $layout
     */
    public function __construct(
        public readonly string $elementId,
        public readonly string $propertyKey,
        public readonly array $layout = [],
        public readonly ?string $rootSource = null,
    ) {
    }
}

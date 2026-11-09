<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Api;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class ContentLayoutMapPropertyRequest
{
    /**
     * @param array<string, mixed> $source
     */
    public function __construct(
        public readonly string $elementId,
        public readonly string $propertyKey,
        public readonly array $source,
        public readonly ?string $expectedVersion,
    ) {
    }
}

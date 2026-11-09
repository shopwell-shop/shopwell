<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Api;

use Shopwell\Core\Framework\Log\Package;

/**
 * Envelope DTO for the persisted unwrap-element mutation action. Carries the optimistic-concurrency
 * token (the layout's updatedAt, null for a never-updated layout); the layout id is a path parameter.
 *
 * @internal
 */
#[Package('framework')]
final class ContentLayoutUnwrapRequest
{
    public function __construct(
        public readonly string $containerElementId,
        public readonly ?string $expectedVersion,
    ) {
    }
}

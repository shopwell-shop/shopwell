<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Resolution;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ResolutionContext
{
    /**
     * @param list<ProvidedContext> $available context available AT this element's position
     */
    public function __construct(
        public string $elementId,
        public array $available,
    ) {
    }
}

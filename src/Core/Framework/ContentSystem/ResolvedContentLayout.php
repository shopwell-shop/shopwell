<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
final readonly class ResolvedContentLayout
{
    private function __construct(
        public string $layoutId,
        public RenderingSpecification $specification,
    ) {
    }

    public static function create(string $layoutId, RenderingSpecification $specification): self
    {
        return new self($layoutId, $specification);
    }
}

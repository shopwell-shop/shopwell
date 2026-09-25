<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Seo;

use Shopwell\Core\Framework\Log\Package;

#[Package('inventory')]
final readonly class ResolvedSeoUrl
{
    public function __construct(
        public string $pathInfo,
        public bool $isCanonical,
        public ?string $id = null,
        public ?string $canonicalPathInfo = null,
        public ?string $seoPathInfo = null,
    ) {
    }
}

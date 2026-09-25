<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Seo;

use Shopwell\Core\Framework\Log\Package;

#[Package('inventory')]
final readonly class SeoUrlRequestContext
{
    public function __construct(
        public string $languageId,
        public string $salesChannelId,
        public string $pathInfo,
        public ?string $queryString = null,
    ) {
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Sitemap\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

#[Package('discovery')]
class UrlResult extends Struct
{
    /**
     * @param Url[] $urls
     */
    public function __construct(
        private readonly array $urls,
        private readonly ?int $nextOffset
    ) {
    }

    /**
     * @return Url[]
     */
    public function getUrls(): array
    {
        return $this->urls;
    }

    public function getNextOffset(): ?int
    {
        return $this->nextOffset;
    }

    public function getApiAlias(): string
    {
        return 'sitemap_url_result';
    }
}

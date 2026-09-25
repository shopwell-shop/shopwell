<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Sitemap;

use Shopwell\Core\Content\Sitemap\Struct\Sitemap;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

#[Package('discovery')]
class SitemapPage extends Struct
{
    /**
     * @var array<Sitemap>
     */
    protected array $sitemaps;

    /**
     * @return array<Sitemap>
     */
    public function getSitemaps(): array
    {
        return $this->sitemaps;
    }

    /**
     * @param array<Sitemap> $sitemaps
     */
    public function setSitemaps(array $sitemaps): void
    {
        $this->sitemaps = $sitemaps;
    }
}

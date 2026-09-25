<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Sitemap\Service;

use Shopwell\Core\Content\Sitemap\Struct\Sitemap;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('discovery')]
interface SitemapListerInterface
{
    /**
     * @return Sitemap[]
     */
    public function getSitemaps(SalesChannelContext $salesChannelContext): array;
}

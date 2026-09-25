<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Sitemap\SalesChannel;

use Shopwell\Core\Content\Sitemap\Struct\SitemapCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<SitemapCollection>
 */
#[Package('discovery')]
class SitemapRouteResponse extends StoreApiResponse
{
    public function getSitemaps(): SitemapCollection
    {
        return $this->object;
    }
}

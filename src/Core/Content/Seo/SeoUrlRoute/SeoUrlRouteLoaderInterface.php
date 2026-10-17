<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Seo\SeoUrlRoute;

use Shopwell\Core\Framework\Log\Package;

#[Package('inventory')]
interface SeoUrlRouteLoaderInterface
{
    /**
     * @return iterable<SeoUrlRouteInterface>
     */
    public function load(): iterable;
}

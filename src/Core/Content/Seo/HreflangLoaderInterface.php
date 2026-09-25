<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Seo;

use Shopwell\Core\Content\Seo\Hreflang\HreflangCollection;
use Shopwell\Core\Framework\Log\Package;

#[Package('inventory')]
interface HreflangLoaderInterface
{
    public function load(HreflangLoaderParameter $parameter): HreflangCollection;
}

<?php declare(strict_types=1);

namespace Shopwell\Storefront\Pagelet\Menu\Offcanvas;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('discovery')]
interface MenuOffcanvasPageletLoaderInterface
{
    public function load(Request $request, SalesChannelContext $context): MenuOffcanvasPagelet;
}

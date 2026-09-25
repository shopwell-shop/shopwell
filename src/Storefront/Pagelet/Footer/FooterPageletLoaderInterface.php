<?php declare(strict_types=1);

namespace Shopwell\Storefront\Pagelet\Footer;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('discovery')]
interface FooterPageletLoaderInterface
{
    public function load(Request $request, SalesChannelContext $salesChannelContext): FooterPagelet;
}

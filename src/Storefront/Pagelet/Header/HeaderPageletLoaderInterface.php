<?php declare(strict_types=1);

namespace Shopwell\Storefront\Pagelet\Header;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('discovery')]
interface HeaderPageletLoaderInterface
{
    public function load(Request $request, SalesChannelContext $context): HeaderPagelet;
}

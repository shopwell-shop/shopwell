<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Cms\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route can be used to load a single resolved cms page of the authenticated sales channel.
 */
#[Package('discovery')]
abstract class AbstractCmsRoute
{
    abstract public function getDecorated(): AbstractCmsRoute;

    abstract public function load(string $id, Request $request, SalesChannelContext $context): CmsRouteResponse;
}

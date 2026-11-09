<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route returns content layout data in the configured output format.
 *
 * @internal
 */
#[Package('framework')]
abstract class AbstractContentRoute
{
    abstract public function getDecorated(): AbstractContentRoute;

    abstract public function load(string $path, Request $request, SalesChannelContext $context): AbstractContentRouteResponse;
}

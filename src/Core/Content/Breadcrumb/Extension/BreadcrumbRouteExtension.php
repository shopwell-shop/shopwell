<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Breadcrumb\Extension;

use Shopwell\Core\Content\Breadcrumb\SalesChannel\BreadcrumbRouteResponse;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<BreadcrumbRouteResponse>
 */
#[Package('inventory')]
final class BreadcrumbRouteExtension extends Extension
{
    public const NAME = 'breadcrumb-route.load';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly SalesChannelContext $salesChannelContext,
    ) {
    }
}

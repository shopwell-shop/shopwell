<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Category\Extension;

use Shopwell\Core\Content\Category\SalesChannel\CategoryRouteResponse;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CategoryRouteResponse>
 */
#[Package('discovery')]
final class CategoryRouteExtension extends Extension
{
    public const NAME = 'category-route.load';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $navigationId,
        public readonly Request $request,
        public readonly SalesChannelContext $context,
    ) {
    }
}

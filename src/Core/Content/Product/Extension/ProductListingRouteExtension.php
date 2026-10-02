<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Extension;

use Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ProductListingRouteResponse>
 */
#[Package('inventory')]
final class ProductListingRouteExtension extends Extension
{
    public const NAME = 'product-listing-route.load';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $categoryId,
        public readonly Request $request,
        public readonly SalesChannelContext $context,
        public readonly Criteria $criteria,
    ) {
    }
}

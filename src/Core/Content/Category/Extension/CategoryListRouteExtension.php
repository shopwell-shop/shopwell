<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Category\Extension;

use Shopwell\Core\Content\Category\SalesChannel\CategoryListRouteResponse;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CategoryListRouteResponse>
 */
#[Package('discovery')]
final class CategoryListRouteExtension extends Extension
{
    public const NAME = 'category-list-route.load';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Criteria $criteria,
        public readonly SalesChannelContext $context,
    ) {
    }
}

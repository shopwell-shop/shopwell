<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Extension;

use Shopwell\Core\Content\Product\SalesChannel\Garan\GaranLabelRouteResponse;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<GaranLabelRouteResponse>
 */
#[Package('inventory')]
final class GaranLabelRouteExtension extends Extension
{
    public const NAME = 'garan-label-route.load';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $productId,
        public readonly SalesChannelContext $context,
    ) {
    }
}

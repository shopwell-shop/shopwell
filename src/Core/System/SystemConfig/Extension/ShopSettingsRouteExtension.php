<?php declare(strict_types=1);

namespace Shopwell\Core\System\SystemConfig\Extension;

use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\SalesChannel\ShopSettingsRouteResponse;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ShopSettingsRouteResponse>
 */
#[Package('framework')]
final class ShopSettingsRouteExtension extends Extension
{
    public const NAME = 'shop-settings-route.load';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly SalesChannelContext $context,
    ) {
    }
}

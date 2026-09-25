<?php declare(strict_types=1);

namespace Shopwell\Core\System\SystemConfig\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ShopSettings>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ShopSettingsRouteResponse extends StoreApiResponse
{
    public function getSettings(): ShopSettings
    {
        return $this->object;
    }
}

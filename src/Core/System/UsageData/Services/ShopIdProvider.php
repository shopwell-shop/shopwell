<?php declare(strict_types=1);

namespace Shopwell\Core\System\UsageData\Services;

use Shopwell\Core\Framework\App\ShopId\ShopId;
use Shopwell\Core\Framework\App\ShopId\ShopIdProvider as AppSystemShopIdProvider;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('data-services')]
readonly class ShopIdProvider
{
    public function __construct(
        private AppSystemShopIdProvider $shopIdProvider,
        private SystemConfigService $systemConfigService,
    ) {
    }

    public function getShopId(): string
    {
        $shopId = $this->systemConfigService->get(AppSystemShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY_V2)
            ?? $this->systemConfigService->get(AppSystemShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY);

        if (\is_array($shopId)) {
            return ShopId::fromSystemConfig($shopId)->id;
        }

        return $this->shopIdProvider->getShopId()->id;
    }
}

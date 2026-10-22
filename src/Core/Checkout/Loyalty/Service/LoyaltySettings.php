<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Service;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SystemConfig\SystemConfigService;

/**
 * The membership settings of the shop, read in one place.
 *
 * The defaults the settings card shows are repeated here on purpose: a key the shop never saved is
 * `null`, not its declared default. Read without a fallback, `pointValueInCurrency` turned into "one
 * point is worth 0" and `maxRedeemablePercent` into "points may cover 0 % of the order", which made
 * the whole redemption look broken while the settings card showed usable values.
 *
 * @final
 */
#[Package('checkout')]
class LoyaltySettings
{
    final public const CONFIG_KEY_ENABLED = 'core.loyalty.enabled';

    final public const CONFIG_KEY_POINTS_PER_CURRENCY_UNIT = 'core.loyalty.pointsPerCurrencyUnit';

    final public const CONFIG_KEY_GROWTH_PER_CURRENCY_UNIT = 'core.loyalty.growthPerCurrencyUnit';

    final public const CONFIG_KEY_POINT_VALUE_IN_CURRENCY = 'core.loyalty.pointValueInCurrency';

    final public const CONFIG_KEY_MIN_REDEEMABLE_POINTS = 'core.loyalty.minRedeemablePoints';

    final public const CONFIG_KEY_MAX_REDEEMABLE_PERCENT = 'core.loyalty.maxRedeemablePercent';

    final public const CONFIG_KEY_POINTS_VALIDITY_DAYS = 'core.loyalty.pointsValidityDays';

    final public const CONFIG_KEY_TIER_AUTO_GROUP_SYNC = 'core.loyalty.tierAutoGroupSync';

    final public const DEFAULT_ENABLED = true;

    final public const DEFAULT_POINTS_PER_CURRENCY_UNIT = 1;

    final public const DEFAULT_GROWTH_PER_CURRENCY_UNIT = 1;

    final public const DEFAULT_POINT_VALUE_IN_CURRENCY = 0.01;

    final public const DEFAULT_MIN_REDEEMABLE_POINTS = 100;

    final public const DEFAULT_MAX_REDEEMABLE_PERCENT = 50.0;

    final public const DEFAULT_POINTS_VALIDITY_DAYS = 365;

    final public const DEFAULT_TIER_AUTO_GROUP_SYNC = false;

    /**
     * @internal
     */
    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    /**
     * Whether the shop runs the membership at all. Earning is gated by this, redeeming what an order
     * already took is not: the points of an accepted order must be booked either way.
     */
    public function isEnabled(?string $salesChannelId = null): bool
    {
        $value = $this->systemConfigService->get(self::CONFIG_KEY_ENABLED, $salesChannelId);

        return $value === null ? self::DEFAULT_ENABLED : (bool) $value;
    }

    public function pointsPerCurrencyUnit(?string $salesChannelId = null): int
    {
        return $this->int(self::CONFIG_KEY_POINTS_PER_CURRENCY_UNIT, $salesChannelId)
            ?? self::DEFAULT_POINTS_PER_CURRENCY_UNIT;
    }

    public function growthPerCurrencyUnit(?string $salesChannelId = null): int
    {
        return $this->int(self::CONFIG_KEY_GROWTH_PER_CURRENCY_UNIT, $salesChannelId)
            ?? self::DEFAULT_GROWTH_PER_CURRENCY_UNIT;
    }

    /**
     * What a single point is worth when it is redeemed, in the currency of the order.
     */
    public function pointValueInCurrency(?string $salesChannelId = null): float
    {
        return $this->float(self::CONFIG_KEY_POINT_VALUE_IN_CURRENCY, $salesChannelId)
            ?? self::DEFAULT_POINT_VALUE_IN_CURRENCY;
    }

    public function minRedeemablePoints(?string $salesChannelId = null): int
    {
        return $this->int(self::CONFIG_KEY_MIN_REDEEMABLE_POINTS, $salesChannelId)
            ?? self::DEFAULT_MIN_REDEEMABLE_POINTS;
    }

    /**
     * The share of the goods subtotal that may be paid with points, in percent.
     */
    public function maxRedeemablePercent(?string $salesChannelId = null): float
    {
        return $this->float(self::CONFIG_KEY_MAX_REDEEMABLE_PERCENT, $salesChannelId)
            ?? self::DEFAULT_MAX_REDEEMABLE_PERCENT;
    }

    /**
     * How long awarded points live. Zero means they never expire.
     */
    public function pointsValidityDays(?string $salesChannelId = null): int
    {
        return $this->int(self::CONFIG_KEY_POINTS_VALIDITY_DAYS, $salesChannelId)
            ?? self::DEFAULT_POINTS_VALIDITY_DAYS;
    }

    /**
     * Whether reaching a tier also moves the customer into the customer group the tier points at.
     */
    public function tierAutoGroupSync(?string $salesChannelId = null): bool
    {
        $value = $this->systemConfigService->get(self::CONFIG_KEY_TIER_AUTO_GROUP_SYNC, $salesChannelId);

        return $value === null ? self::DEFAULT_TIER_AUTO_GROUP_SYNC : (bool) $value;
    }

    private function int(string $key, ?string $salesChannelId): ?int
    {
        $value = $this->systemConfigService->get($key, $salesChannelId);

        return is_numeric($value) ? (int) $value : null;
    }

    private function float(string $key, ?string $salesChannelId): ?float
    {
        $value = $this->systemConfigService->get($key, $salesChannelId);

        return is_numeric($value) ? (float) $value : null;
    }
}

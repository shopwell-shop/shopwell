<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Service;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SystemConfig\SystemConfigService;

/**
 * The wallet settings of the shop, read in one place.
 *
 * The defaults the settings card shows are repeated here on purpose: a key the shop never saved is
 * `null`, not its declared default, and every caller used to invent its own fallback. The most
 * expensive one was `maxPaymentPercent`: unset meant "0 % of the order total may be paid with the
 * wallet", which limited the wallet so hard that it looked switched off.
 *
 * @final
 */
#[Package('checkout')]
class WalletSettings
{
    final public const CONFIG_KEY_ENABLED = 'core.wallet.enabled';

    final public const CONFIG_KEY_MAX_PAYMENT_PERCENT = 'core.wallet.maxPaymentPercent';

    final public const CONFIG_KEY_MIN_TOP_UP_AMOUNT = 'core.wallet.minTopUpAmount';

    final public const CONFIG_KEY_MAX_TOP_UP_AMOUNT = 'core.wallet.maxTopUpAmount';

    final public const CONFIG_KEY_TOP_UP_PRESETS = 'core.wallet.topUpPresets';

    final public const DEFAULT_ENABLED = true;

    final public const DEFAULT_MAX_PAYMENT_PERCENT = 100.0;

    final public const DEFAULT_MIN_TOP_UP_AMOUNT = 0.0;

    final public const DEFAULT_MAX_TOP_UP_AMOUNT = 0.0;

    final public const DEFAULT_TOP_UP_PRESETS = '100,200,500,1000,2000';

    /**
     * @internal
     */
    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    /**
     * Whether the shop offers the wallet at all. Everything that creates a wallet payment, a top-up or
     * a wallet balance entry for a new order is gated by this; the settlement of payments that were
     * already accepted is not, because a customer must never lose money the shop already took.
     */
    public function isEnabled(?string $salesChannelId = null): bool
    {
        $value = $this->systemConfigService->get(self::CONFIG_KEY_ENABLED, $salesChannelId);

        return $value === null ? self::DEFAULT_ENABLED : (bool) $value;
    }

    /**
     * The share of the goods subtotal that may be paid with the wallet, in percent.
     */
    public function maxPaymentPercent(?string $salesChannelId = null): float
    {
        return $this->float(self::CONFIG_KEY_MAX_PAYMENT_PERCENT, $salesChannelId)
            ?? self::DEFAULT_MAX_PAYMENT_PERCENT;
    }

    public function minTopUpAmount(?string $salesChannelId = null): float
    {
        return $this->float(self::CONFIG_KEY_MIN_TOP_UP_AMOUNT, $salesChannelId)
            ?? self::DEFAULT_MIN_TOP_UP_AMOUNT;
    }

    /**
     * Zero means "no limit", which is why it is not treated as a maximum of zero.
     */
    public function maxTopUpAmount(?string $salesChannelId = null): float
    {
        return $this->float(self::CONFIG_KEY_MAX_TOP_UP_AMOUNT, $salesChannelId)
            ?? self::DEFAULT_MAX_TOP_UP_AMOUNT;
    }

    /**
     * The amounts the storefront offers next to the free input, ascending and without duplicates.
     *
     * @return list<float>
     */
    public function topUpPresets(?string $salesChannelId = null): array
    {
        $configured = $this->systemConfigService->getString(self::CONFIG_KEY_TOP_UP_PRESETS, $salesChannelId);

        if (trim($configured) === '') {
            $configured = self::DEFAULT_TOP_UP_PRESETS;
        }

        $presets = [];

        foreach (explode(',', $configured) as $value) {
            $value = trim($value);

            if ($value === '' || !is_numeric($value) || (float) $value <= 0.0) {
                continue;
            }

            $presets[] = (float) $value;
        }

        $presets = array_values(array_unique($presets));
        sort($presets);

        return $presets;
    }

    private function float(string $key, ?string $salesChannelId): ?float
    {
        $value = $this->systemConfigService->get($key, $salesChannelId);

        return is_numeric($value) ? (float) $value : null;
    }
}

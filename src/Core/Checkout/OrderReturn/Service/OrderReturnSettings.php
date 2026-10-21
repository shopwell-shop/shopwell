<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\Service;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SystemConfig\SystemConfigService;

/**
 * The return settings of the shop, read in one place.
 *
 * The declared defaults are repeated here on purpose: a key the shop never saved is `null` and not
 * its declared default, so every caller used to invent its own fallback. `windowDays` in particular
 * must not fall back to `0`, which would read as "no returns at all" and lock the storefront without
 * anyone noticing.
 *
 * @final
 */
#[Package('after-sales')]
class OrderReturnSettings
{
    final public const CONFIG_KEY_ENABLED = 'core.orderReturn.enabled';

    final public const CONFIG_KEY_ALLOW_GUEST = 'core.orderReturn.allowGuest';

    final public const CONFIG_KEY_WINDOW_DAYS = 'core.orderReturn.windowDays';

    final public const CONFIG_KEY_WINDOW_STARTS_AT_DELIVERY = 'core.orderReturn.windowStartsAtDelivery';

    final public const CONFIG_KEY_APPROVAL_DEADLINE_HOURS = 'core.orderReturn.approvalDeadlineHours';

    final public const CONFIG_KEY_AUTO_APPROVE = 'core.orderReturn.autoApprove';

    final public const CONFIG_KEY_RETURN_DEADLINE_DAYS = 'core.orderReturn.returnDeadlineDays';

    final public const CONFIG_KEY_RESTOCK_ON_RECEIVE = 'core.orderReturn.restockOnReceive';

    final public const DEFAULT_ENABLED = true;

    final public const DEFAULT_ALLOW_GUEST = true;

    final public const DEFAULT_WINDOW_DAYS = 30;

    final public const DEFAULT_WINDOW_STARTS_AT_DELIVERY = true;

    final public const DEFAULT_APPROVAL_DEADLINE_HOURS = 48;

    final public const DEFAULT_AUTO_APPROVE = false;

    final public const DEFAULT_RETURN_DEADLINE_DAYS = 14;

    final public const DEFAULT_RESTOCK_ON_RECEIVE = true;

    /**
     * @internal
     */
    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    public function isEnabled(?string $salesChannelId = null): bool
    {
        $value = $this->systemConfigService->get(self::CONFIG_KEY_ENABLED, $salesChannelId);

        return $value === null ? self::DEFAULT_ENABLED : (bool) $value;
    }

    public function allowGuest(?string $salesChannelId = null): bool
    {
        $value = $this->systemConfigService->get(self::CONFIG_KEY_ALLOW_GUEST, $salesChannelId);

        return $value === null ? self::DEFAULT_ALLOW_GUEST : (bool) $value;
    }

    public function windowDays(?string $salesChannelId = null): int
    {
        return $this->int(self::CONFIG_KEY_WINDOW_DAYS, $salesChannelId) ?? self::DEFAULT_WINDOW_DAYS;
    }

    /**
     * Whether the window counts from the delivery and not from the order itself. A shop that never
     * shipped anything cannot run a return window on the order date.
     */
    public function windowStartsAtDelivery(?string $salesChannelId = null): bool
    {
        $value = $this->systemConfigService->get(self::CONFIG_KEY_WINDOW_STARTS_AT_DELIVERY, $salesChannelId);

        return $value === null ? self::DEFAULT_WINDOW_STARTS_AT_DELIVERY : (bool) $value;
    }

    public function approvalDeadlineHours(?string $salesChannelId = null): int
    {
        return $this->int(self::CONFIG_KEY_APPROVAL_DEADLINE_HOURS, $salesChannelId) ?? self::DEFAULT_APPROVAL_DEADLINE_HOURS;
    }

    /**
     * Whether an approval that ran out of time is approved automatically. Declining automatically is
     * never offered: a customer must not lose a claim because a merchant was on holiday.
     */
    public function autoApprove(?string $salesChannelId = null): bool
    {
        $value = $this->systemConfigService->get(self::CONFIG_KEY_AUTO_APPROVE, $salesChannelId);

        return $value === null ? self::DEFAULT_AUTO_APPROVE : (bool) $value;
    }

    public function returnDeadlineDays(?string $salesChannelId = null): int
    {
        return $this->int(self::CONFIG_KEY_RETURN_DEADLINE_DAYS, $salesChannelId) ?? self::DEFAULT_RETURN_DEADLINE_DAYS;
    }

    public function restockOnReceive(?string $salesChannelId = null): bool
    {
        $value = $this->systemConfigService->get(self::CONFIG_KEY_RESTOCK_ON_RECEIVE, $salesChannelId);

        return $value === null ? self::DEFAULT_RESTOCK_ON_RECEIVE : (bool) $value;
    }

    private function int(string $key, ?string $salesChannelId): ?int
    {
        $value = $this->systemConfigService->get($key, $salesChannelId);

        return is_numeric($value) ? (int) $value : null;
    }
}

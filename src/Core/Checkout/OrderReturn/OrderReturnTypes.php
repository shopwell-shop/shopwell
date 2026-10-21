<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn;

use Shopwell\Core\Framework\Log\Package;

/**
 * The two resolutions the core knows: money back with the goods, money back without them.
 *
 * It mirrors the refund of shopify, which may be issued for a return or without one at all. The type
 * is a field of the return and not a second table, because both are the same request with the same
 * decision and the same money - only the parcel differs.
 *
 * Everything else a shop may offer - a replacement, a repair, a voucher - is a resolution a plugin
 * adds. The core deliberately names no such type: a type the core does not understand would have to
 * be given a flow here, and that flow belongs to whoever sells it.
 *
 * @final
 */
#[Package('after-sales')]
class OrderReturnTypes
{
    /**
     * The goods stay with the customer, the money goes back. Meant for an order that never arrived or
     * arrived unusable - a return parcel would be pointless.
     */
    final public const REFUND_ONLY = 'refund_only';

    /**
     * The goods travel back, then the money goes back.
     */
    final public const RETURN_REFUND = 'return_refund';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::REFUND_ONLY,
            self::RETURN_REFUND,
        ];
    }

    public static function requiresReturn(string $type): bool
    {
        return $type === self::RETURN_REFUND;
    }

    /**
     * Whether the money may already go back while the request is only approved, i.e. whether the
     * goods do not have to arrive first.
     */
    public static function refundableOnApproval(string $type): bool
    {
        return $type === self::REFUND_ONLY;
    }
}

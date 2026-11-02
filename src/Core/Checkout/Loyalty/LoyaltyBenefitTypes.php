<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty;

use Shopwell\Core\Framework\Log\Package;

/**
 * The benefits a tier can grant.
 *
 * The values are persisted, so they are part of the data and not free to rename. Each type owns the
 * meaning of its `value`: a multiplier is a factor, a discount a percentage, free shipping the number 1,
 * a birthday gift an amount in the shop currency and a point lifetime a number of days. Keeping one
 * numeric value per type is what lets a new benefit be added without touching the table - the type
 * carries the interpretation, not the column.
 *
 * A type that is unknown to this class is not an error: it reads back as a row nobody asks for, so a
 * benefit an extension invented stays stored and visible instead of being rejected on save.
 */
#[Package('checkout')]
final class LoyaltyBenefitTypes
{
    /**
     * How many points a unit of currency earns on top of the base rate. `1.5` means a tier member
     * collects half again as many points as a customer without a tier.
     */
    public const EARN_MULTIPLIER = 'earn_multiplier';

    /**
     * A percentage taken off the cart total for tier members, expressed as `5` for five percent.
     */
    public const CART_DISCOUNT = 'cart_discount';

    /**
     * Waives the shipping costs. The value is `1` - it exists so "granted" is a row, not a column that
     * has to be read as a boolean and cannot be extended later.
     */
    public const FREE_SHIPPING = 'free_shipping';

    /**
     * An amount granted once a year on the customer's birthday, in the shop currency.
     */
    public const BIRTHDAY_GIFT = 'birthday_gift';

    /**
     * How long the points this tier earns stay valid, in days. Overrides the shop wide setting for the
     * customers of this tier only.
     */
    public const POINTS_EXPIRY_DAYS = 'points_expiry_days';
}

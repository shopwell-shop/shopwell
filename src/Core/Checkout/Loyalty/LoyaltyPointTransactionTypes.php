<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty;

use Shopwell\Core\Framework\Log\Package;

/**
 * The kinds of entry the point ledger knows.
 *
 * They are persisted as they are, so the values are part of the data and not free to rename: a stored
 * `revoke` that is looked up as `revoked` is a booking the administration cannot label any more.
 *
 * The two return types are deliberately separate instead of one "refund" type, because they move in
 * opposite directions and answer different questions: `return` gives the customer back the points an
 * order spent, `revoke` takes away the points an order earned. A statement that mixed them could not
 * tell a customer why their balance fell after a return.
 */
#[Package('checkout')]
final class LoyaltyPointTransactionTypes
{
    /**
     * An order completed and the shop granted points for it.
     */
    public const AWARD = 'award';

    /**
     * The customer paid part of an order with points.
     */
    public const REDEEM = 'redeem';

    /**
     * An administrator corrected the balance by hand.
     */
    public const ADJUST = 'adjust';

    /**
     * The points of a batch ran out and were removed from the balance.
     */
    public const EXPIRE = 'expire';

    /**
     * Goods came back and the points the order had spent were given back into the batches they left.
     */
    public const RETURN = 'return';

    /**
     * Goods came back and the points the order had earned were taken back - as far as the batches of
     * that order still hold them.
     */
    public const REVOKE = 'revoke';
}

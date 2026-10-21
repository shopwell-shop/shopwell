<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn;

use Shopwell\Core\Framework\Log\Package;

/**
 * The state machine of a return, the counter part of `order.state`.
 *
 * A return runs the order backwards: the customer requests it, the shop decides, the goods travel
 * back and the money follows. The five names a merchant already knows from the return handling of
 * shopify are kept as they are - `requested`, `open`, `closed`, `declined`, `canceled` - so the
 * vocabulary of the shop does not have to be relearned. The states in between are the ones a parcel
 * and an asynchronous refund really have: `returning` while it is on its way, `received` once it
 * arrived and `refunding` while the payment gateway is paying out.
 *
 * Which of the two ways a return takes is decided by its type and not by the machine - a refund
 * without goods jumps from `open` straight to `refunding`, a return needs the goods first.
 *
 * The constants are action names, never target states: `approve` is what the shop does, and the
 * state it leads to is written down in the migration.
 *
 * @final
 */
#[Package('after-sales')]
class OrderReturnStates
{
    final public const STATE_MACHINE = 'order_return.state';

    /**
     * The customer asked for it, the shop has not decided yet.
     */
    final public const STATE_REQUESTED = 'requested';

    /**
     * The shop agreed and the customer may send the goods back. A refund without goods is payable
     * from here already.
     */
    final public const STATE_OPEN = 'open';

    /**
     * The customer announced the return, the parcel is on its way.
     */
    final public const STATE_RETURNING = 'returning';

    /**
     * The parcel arrived and was accepted, the money may go back.
     */
    final public const STATE_RECEIVED = 'received';

    /**
     * The refund was handed to the payment gateway.
     */
    final public const STATE_REFUNDING = 'refunding';

    /**
     * The money went back and everything after it is done.
     */
    final public const STATE_CLOSED = 'closed';

    /**
     * The shop declined. A declined return can not be refunded afterwards and is final.
     */
    final public const STATE_DECLINED = 'declined';

    /**
     * The customer withdrew it, or it ran out of time. Final as well.
     */
    final public const STATE_CANCELED = 'canceled';

    final public const ACTION_APPROVE = 'approve';
    final public const ACTION_DECLINE = 'decline';
    final public const ACTION_CANCEL = 'cancel';
    final public const ACTION_SHIP = 'ship';
    final public const ACTION_RECEIVE = 'receive';
    final public const ACTION_REFUND = 'refund';
    final public const ACTION_FINISH = 'finish';

    /**
     * The states a return can no longer leave. A new return for the same line items may be opened
     * from here, which is what the quantity guard counts on.
     *
     * @return list<string>
     */
    public static function finalStates(): array
    {
        return [
            self::STATE_CLOSED,
            self::STATE_DECLINED,
            self::STATE_CANCELED,
        ];
    }

    /**
     * The states that still hold a claim on the goods, i.e. the ones the quantity guard has to
     * subtract from what is returnable. A return that was declined or withdrawn frees its quantity
     * again, one that is closed has really taken the goods back.
     *
     * @return list<string>
     */
    public static function blockingStates(): array
    {
        return [
            self::STATE_REQUESTED,
            self::STATE_OPEN,
            self::STATE_RETURNING,
            self::STATE_RECEIVED,
            self::STATE_REFUNDING,
            self::STATE_CLOSED,
        ];
    }
}

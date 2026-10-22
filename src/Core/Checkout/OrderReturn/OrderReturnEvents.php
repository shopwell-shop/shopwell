<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn;

use Shopwell\Core\Framework\Log\Package;

/**
 * The events an return dispatches. They are the seam a plugin listens on: whatever it wants to do
 * on top of an return - notify somebody, hand out points, open a repair ticket - subscribes here
 * and never touches the return itself.
 *
 * Every state change is already published by the platform through the `StateMachineTransitionEvent`,
 * which carries the technical names of the machine and is what a plugin should subscribe to when it
 * only cares about one state.
 *
 * @final
 */
#[Package('after-sales')]
class OrderReturnEvents
{
    /**
     * A customer opened an return, it is waiting for the decision of the shop.
     */
    final public const RETURN_CREATED_EVENT = 'order_return.created';

    /**
     * The return reached its end - the money went back and everything after it is done.
     */
    final public const RETURN_COMPLETED_EVENT = 'order_return.completed';

    /**
     * The customer withdrew the return or it ran out of time.
     */
    final public const RETURN_CANCELLED_EVENT = 'order_return.cancelled';
}

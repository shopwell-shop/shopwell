<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Membership;

use Shopwell\Core\Framework\Log\Package;

/**
 * The life of a membership: it runs, and at some point it does not anymore.
 *
 * Only two states, because that is the whole life cycle - a membership is either in force or it is
 * over. Why it is over is a different question with a different answer per case (`expired`,
 * `superseded`, `revoked`) and is kept next to the state instead of inside it: every query that asks
 * "does this customer have a membership" would otherwise have to list the ways a membership can end,
 * and would have to be touched again each time a new way is added.
 *
 * @final
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class MembershipSubscriptionStates
{
    /**
     * The membership is in force right now, or starts today.
     */
    final public const STATE_ACTIVE = 'active';

    /**
     * The membership is over. `ended_at` says when, `end_reason` says why.
     */
    final public const STATE_ENDED = 'ended';

    /**
     * The paid period ran out. This is what the daily task writes.
     */
    final public const END_EXPIRED = 'expired';

    /**
     * A membership of another tier was bought, so the running one had to give way. The days that
     * were left are not carried over: a tier is not a currency.
     */
    final public const END_SUPERSEDED = 'superseded';

    /**
     * An operator ended it early - a refund, a goodwill decision, a purchase that never happened.
     */
    final public const END_REVOKED = 'revoked';
}

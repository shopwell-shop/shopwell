<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Subscription;

use Shopwell\Core\Framework\Log\Package;

/**
 * Owns the database boundary of a cycle write. Implementations lock the cycle first and its
 * subscription second, then run the callback while both locks are held.
 *
 * @internal
 */
#[Package('framework')]
interface PaymentCycleTransactionInterface
{
    /**
     * @template TReturn
     *
     * @param \Closure(): TReturn $callback
     *
     * @return TReturn
     */
    public function execute(string $cycleId, bool $withSubscription, \Closure $callback): mixed;
}

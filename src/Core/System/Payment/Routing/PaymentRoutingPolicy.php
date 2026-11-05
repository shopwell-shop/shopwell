<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Routing;

use Shopwell\Core\Framework\Log\Package;

/**
 * Register with shopwell.payment.routing_policy, keyed by getName().
 */
#[Package('framework')]
interface PaymentRoutingPolicy
{
    public function getName(): string;

    /**
     * Candidates share one payment method and have already passed account authorization and eligibility.
     *
     * @param non-empty-list<PaymentRoute> $candidates
     */
    public function choose(array $candidates, PaymentContext $context): PaymentRoute;
}

<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Routing;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class DefaultPaymentRoutingPolicy implements PaymentRoutingPolicy
{
    public function getName(): string
    {
        return 'priority';
    }

    public function choose(array $candidates, PaymentContext $context): PaymentRoute
    {
        $selected = $candidates[0];
        foreach ($candidates as $candidate) {
            if ($candidate->config->priority < $selected->config->priority) {
                $selected = $candidate;
            }
        }

        return $selected;
    }
}

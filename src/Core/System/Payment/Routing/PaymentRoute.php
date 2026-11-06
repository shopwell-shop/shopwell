<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Routing;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class PaymentRoute
{
    public function __construct(
        public ResolvedPaymentConfig $config,
        public PaymentContext $context,
        public string $interaction,
        public string $policyName = 'priority',
    ) {
    }
}

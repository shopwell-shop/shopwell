<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;

/**
 * An action that declares whatever capability the test needs and never executes.
 *
 * @internal
 */
#[Package('framework')]
final class TestPaymentAction extends AbstractPaymentAction
{
    public function __construct(private readonly PaymentCapability $declares)
    {
    }

    public function capability(): PaymentCapability
    {
        return $this->declares;
    }

    public function execute($request): void
    {
        // the tests never run a gateway
    }

    public function supports($request): bool
    {
        return false;
    }
}

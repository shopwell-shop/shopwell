<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Account\_fixtures;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Account\PaymentAccountLock;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;

/**
 * @internal
 */
#[Package('framework')]
class PaymentAccountLockStub extends PaymentAccountLock
{
    public function __construct()
    {
    }

    public function transaction(\Closure $write)
    {
        return $write();
    }

    public function validateConfiguration(ResolvedPaymentConfig $config): void
    {
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Cashier;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PaymentCashierConfigEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PaymentCashierConfigCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PaymentCashierConfigEntity::class;
    }
}

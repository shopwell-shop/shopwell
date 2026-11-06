<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Cashier;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PaymentCashierSessionEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PaymentCashierSessionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PaymentCashierSessionEntity::class;
    }
}

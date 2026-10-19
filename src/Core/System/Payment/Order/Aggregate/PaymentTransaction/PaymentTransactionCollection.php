<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PaymentTransactionEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PaymentTransactionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'payment_transaction_collection';
    }

    protected function getExpectedClass(): string
    {
        return PaymentTransactionEntity::class;
    }
}

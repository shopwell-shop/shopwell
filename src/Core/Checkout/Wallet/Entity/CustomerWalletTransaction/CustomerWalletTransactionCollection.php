<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CustomerWalletTransactionEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerWalletTransactionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'customer_wallet_transaction_collection';
    }

    protected function getExpectedClass(): string
    {
        return CustomerWalletTransactionEntity::class;
    }
}

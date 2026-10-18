<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTopUp;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CustomerWalletTopUpEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerWalletTopUpCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'customer_wallet_top_up_collection';
    }

    protected function getExpectedClass(): string
    {
        return CustomerWalletTopUpEntity::class;
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CustomerWalletEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerWalletCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'customer_wallet_collection';
    }

    protected function getExpectedClass(): string
    {
        return CustomerWalletEntity::class;
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Transaction\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Collection;

/**
 * @extends Collection<Transaction>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class TransactionCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'cart_transaction_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return Transaction::class;
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Account;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PaymentAccountEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PaymentAccountCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'payment_account_collection';
    }

    protected function getExpectedClass(): string
    {
        return PaymentAccountEntity::class;
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\App\Aggregate\PaymentAppAccount;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PaymentAppAccountEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PaymentAppAccountCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'payment_app_account_collection';
    }

    protected function getExpectedClass(): string
    {
        return PaymentAppAccountEntity::class;
    }
}

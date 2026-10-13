<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\DataAbstractionLayer;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PaymentAppProviderEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PaymentAppProviderCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'payment_app_provider_collection';
    }

    protected function getExpectedClass(): string
    {
        return PaymentAppProviderEntity::class;
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\DataAbstractionLayer;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PaymentProviderConfigEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PaymentProviderConfigCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'payment_provider_config_collection';
    }

    protected function getExpectedClass(): string
    {
        return PaymentProviderConfigEntity::class;
    }
}

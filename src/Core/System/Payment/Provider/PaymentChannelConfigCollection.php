<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PaymentChannelConfigEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PaymentChannelConfigCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'payment_channel_config_collection';
    }

    protected function getExpectedClass(): string
    {
        return PaymentChannelConfigEntity::class;
    }
}

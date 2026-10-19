<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PaymentAppChannelEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PaymentAppChannelCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'payment_app_channel_collection';
    }

    protected function getExpectedClass(): string
    {
        return PaymentAppChannelEntity::class;
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Delivery;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<SmsDeliveryEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SmsDeliveryCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'sms_delivery_collection';
    }

    protected function getExpectedClass(): string
    {
        return SmsDeliveryEntity::class;
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Gateway;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<SmsGatewayEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SmsGatewayCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'sms_gateway_collection';
    }

    protected function getExpectedClass(): string
    {
        return SmsGatewayEntity::class;
    }
}

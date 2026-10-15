<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\App;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PaymentAppEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PaymentAppCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'payment_app_collection';
    }

    protected function getExpectedClass(): string
    {
        return PaymentAppEntity::class;
    }
}

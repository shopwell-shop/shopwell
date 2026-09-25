<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\SalesChannel;

use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<PaymentMethodCollection>>
 */
#[Package('checkout')]
class PaymentMethodRouteResponse extends StoreApiResponse
{
    public function getPaymentMethods(): PaymentMethodCollection
    {
        return $this->object->getEntities();
    }
}

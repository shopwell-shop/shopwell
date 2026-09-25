<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\SalesChannel;

use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<OrderCollection>>
 */
#[Package('checkout')]
class OrderRouteResponse extends StoreApiResponse
{
    /**
     * @var array<string, bool>
     */
    protected array $paymentChangeable = [];

    /**
     * @phpstan-ignore method.childReturnType (Due to the dynamic adding of `paymentChangeable` property, it is not possible to define the correct generic type)
     */
    public function getObject(): ArrayStruct
    {
        return new ArrayStruct([
            'orders' => $this->object,
            'paymentChangeable' => $this->paymentChangeable,
        ], 'order-route-response-struct');
    }

    /**
     * @return EntitySearchResult<OrderCollection>
     */
    public function getOrders(): EntitySearchResult
    {
        return $this->object;
    }

    /**
     * @return array<string, bool>
     */
    public function getPaymentsChangeable(): array
    {
        return $this->paymentChangeable;
    }

    /**
     * @param array<string, bool> $paymentChangeable
     */
    public function setPaymentChangeable(array $paymentChangeable): void
    {
        $this->paymentChangeable = $paymentChangeable;
    }

    /**
     * @param array<string, bool> $paymentChangeable
     */
    public function addPaymentChangeable(array $paymentChangeable): void
    {
        $this->paymentChangeable = array_merge($this->paymentChangeable, $paymentChangeable);
    }
}

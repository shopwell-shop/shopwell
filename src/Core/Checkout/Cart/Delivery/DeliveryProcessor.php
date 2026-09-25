<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Delivery;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartBehavior;
use Shopwell\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopwell\Core\Checkout\Cart\CartProcessorInterface;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopwell\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopwell\Core\Checkout\Cart\Order\IdStruct;
use Shopwell\Core\Checkout\Cart\Order\OrderConverter;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\CheckoutPermissions;
use Shopwell\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Profiling\Profiler;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class DeliveryProcessor implements CartProcessorInterface, CartDataCollectorInterface
{
    final public const MANUAL_SHIPPING_COSTS = 'manualShippingCosts';

    /**
     * @deprecated tag:v6.8.0 - Will be removed and is replaced by {@see CheckoutPermissions::SKIP_PRODUCT_STOCK_VALIDATION}
     */
    final public const SKIP_DELIVERY_PRICE_RECALCULATION = CheckoutPermissions::SKIP_DELIVERY_PRICE_RECALCULATION;

    /**
     * @deprecated tag:v6.8.0 - Will be removed and is replaced by {@see CheckoutPermissions::SKIP_DELIVERY_TAX_RECALCULATION}
     */
    final public const SKIP_DELIVERY_TAX_RECALCULATION = CheckoutPermissions::SKIP_DELIVERY_TAX_RECALCULATION;

    /**
     * @internal
     *
     * @param EntityRepository<ShippingMethodCollection> $shippingMethodRepository
     */
    public function __construct(
        protected DeliveryBuilder $builder,
        protected DeliveryCalculator $deliveryCalculator,
        protected EntityRepository $shippingMethodRepository
    ) {
    }

    public static function buildKey(string $shippingMethodId): string
    {
        return 'shipping-method-' . $shippingMethodId;
    }

    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        Profiler::trace('cart::delivery::collect', function () use ($data, $original, $context): void {
            $default = $context->getShippingMethod()->getId();
            $ids = [];

            if (!$data->has(self::buildKey($default))) {
                $ids = [$default];
            }

            foreach ($original->getDeliveries() as $delivery) {
                $id = $delivery->getShippingMethod()->getId();

                if (!$data->has(self::buildKey($id))) {
                    $ids[] = $id;
                }
            }

            if ($ids === []) {
                return;
            }

            $criteria = (new Criteria($ids))
                ->addAssociations([
                    'prices',
                    'deliveryTime',
                    'tax',
                ])
                ->setTitle('cart::shipping-methods');

            $shippingMethods = $this->shippingMethodRepository->search($criteria, $context->getContext())->getEntities();

            foreach ($ids as $id) {
                $key = self::buildKey($id);

                if (!$shippingMethods->has($id)) {
                    continue;
                }

                $data->set($key, $shippingMethods->get($id));
            }
        }, 'cart');
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        Profiler::trace('cart::delivery::process', function () use ($data, $original, $toCalculate, $context, $behavior): void {
            if ($behavior->hasPermission(self::SKIP_DELIVERY_PRICE_RECALCULATION)) {
                $deliveries = $original->getDeliveries()->filter(static function (Delivery $delivery) {
                    return $delivery->getShippingCosts()->getTotalPrice() >= 0;
                });

                $firstDelivery = $original->getDeliveries()->getPrimaryDelivery(
                    $original->getExtensionOfType(OrderConverter::ORIGINAL_PRIMARY_ORDER_DELIVERY, IdStruct::class)?->getId()
                );

                if (!Feature::isActive('v6.8.0.0')) {
                    $firstDelivery = $deliveries->first();
                }

                if ($firstDelivery === null) {
                    return;
                }

                // Stored original edit shipping cost
                $manualShippingCosts = $toCalculate->getExtension(self::MANUAL_SHIPPING_COSTS) ?? $firstDelivery->getShippingCosts();

                $toCalculate->addExtension(self::MANUAL_SHIPPING_COSTS, $manualShippingCosts);

                if ($manualShippingCosts instanceof CalculatedPrice) {
                    $firstDelivery->setShippingCosts($manualShippingCosts);
                }

                $this->deliveryCalculator->calculate($data, $toCalculate, $deliveries, $context);

                $toCalculate->setDeliveries($deliveries);

                return;
            }

            $deliveries = $this->builder->build($toCalculate, $data, $context, $behavior);
            $manualShippingCosts = $original->getExtension(self::MANUAL_SHIPPING_COSTS);

            if ($manualShippingCosts instanceof CalculatedPrice) {
                $deliveries->first()?->setShippingCosts($manualShippingCosts);
            }

            $this->deliveryCalculator->calculate($data, $toCalculate, $deliveries, $context);

            $toCalculate->setDeliveries($deliveries);
        }, 'cart');
    }
}

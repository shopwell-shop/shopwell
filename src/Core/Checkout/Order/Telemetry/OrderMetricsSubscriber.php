<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\Telemetry;

use Shopwell\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Telemetry\Metrics\Meter;
use Shopwell\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopwell\Core\System\SalesChannel\Telemetry\SalesChannelTypeResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Emits the order placed metrics (order count, line item count) once per placed order.
 *
 * Tagged `shopwell.telemetry.subscriber`, so `TelemetrySubscriberCompilerPass` removes the service
 * entirely when telemetry is disabled.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('checkout')]
readonly class OrderMetricsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Meter $meter,
        private SalesChannelTypeResolver $salesChannelTypeResolver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'emitOrderPlacedMetrics',
        ];
    }

    public function emitOrderPlacedMetrics(CheckoutOrderPlacedEvent $event): void
    {
        $order = $event->getOrder();
        $salesChannelType = $this->salesChannelTypeResolver->resolve(
            $event->getSalesChannelContext()->getSalesChannel()->getTypeId()
        );

        $this->meter->emit(new ConfiguredMetric(
            name: 'order.placed.count',
            value: 1,
            labels: [
                'sales_channel_type' => $salesChannelType,
                'payment_method' => $this->resolvePaymentMethod($order),
            ],
        ));

        $this->meter->emit(new ConfiguredMetric(
            name: 'order.line_items.count',
            value: $order->getLineItems()?->count() ?? 0,
            labels: ['sales_channel_type' => $salesChannelType],
        ));
    }

    /**
     * The payment method technical name is a stable, cross-install identifier, emitted without change.
     * Cardinality is bounded by the `payment_method` label config: core allows its built-in methods, and any other present method
     * collapses to the `other` replacement value; deployments widen `allowed_values` to surface further providers.
     *
     * `none` marks an order with no attributable payment method and kept separate from `other` fallback to identify potential
     * bugs.
     */
    private function resolvePaymentMethod(OrderEntity $order): string
    {
        $technicalName = $order->getTransactions()?->last()?->getPaymentMethod()?->getTechnicalName();

        return $technicalName === null || $technicalName === '' ? 'none' : $technicalName;
    }
}

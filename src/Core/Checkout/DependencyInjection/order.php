<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Cart\CartRuleLoader;
use Shopwell\Core\Checkout\Cart\CartSerializationCleaner;
use Shopwell\Core\Checkout\Cart\Order\LineItemDownloadLoader;
use Shopwell\Core\Checkout\Cart\Order\OrderConverter;
use Shopwell\Core\Checkout\Cart\Order\OrderPersister;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartService;
use Shopwell\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopwell\Core\Checkout\Customer\Service\GuestAuthenticator;
use Shopwell\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRoute;
use Shopwell\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTag\OrderTagDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureStateHandler;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStateHandler;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefundPosition\OrderTransactionCaptureRefundPositionDefinition;
use Shopwell\Core\Checkout\Order\Api\OrderActionController;
use Shopwell\Core\Checkout\Order\Listener\OrderStateChangeEventListener;
use Shopwell\Core\Checkout\Order\OrderAddressService;
use Shopwell\Core\Checkout\Order\OrderDefinition;
use Shopwell\Core\Checkout\Order\OrderExceptionHandler;
use Shopwell\Core\Checkout\Order\SalesChannel\CancelOrderRoute;
use Shopwell\Core\Checkout\Order\SalesChannel\OrderRoute;
use Shopwell\Core\Checkout\Order\SalesChannel\OrderService;
use Shopwell\Core\Checkout\Order\SalesChannel\SetPaymentOrderRoute;
use Shopwell\Core\Checkout\Order\Subscriber\OrderSalutationSubscriber;
use Shopwell\Core\Checkout\Order\Telemetry\OrderMetricsSubscriber;
use Shopwell\Core\Checkout\Order\Validation\OrderValidationFactory;
use Shopwell\Core\Checkout\Payment\Cart\PaymentRefundProcessor;
use Shopwell\Core\Framework\Event\BusinessEventCollector;
use Shopwell\Core\Framework\Telemetry\Metrics\Meter;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\Telemetry\SalesChannelTypeResolver;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(OrderDefinition::class)
        ->tag('shopware.entity.definition')
        ->tag('shopware.entity.hookable');

    $services->set(OrderAddressDefinition::class)
        ->tag('shopware.entity.definition')
        ->tag('shopware.entity.hookable');

    $services->set(OrderExceptionHandler::class)
        ->tag('shopware.dal.exception_handler');

    $services->set(OrderCustomerDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(OrderDeliveryDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(OrderDeliveryPositionDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(OrderLineItemDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(OrderLineItemDownloadDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(OrderTagDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(OrderTransactionDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(OrderTransactionCaptureDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(OrderTransactionCaptureRefundDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(OrderTransactionCaptureRefundPositionDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(OrderService::class)
        ->args([
            service(DataValidator::class),
            service(OrderValidationFactory::class),
            service('event_dispatcher'),
            service(CartService::class),
            service('payment_method.repository'),
            service(StateMachineRegistry::class),
        ]);

    $services->set(OrderValidationFactory::class)
        ->args([
            service(SystemConfigService::class),
        ]);

    $services->set(OrderPersister::class)
        ->args([
            service('order.repository'),
            service(OrderConverter::class),
            service(CartSerializationCleaner::class),
        ]);

    $services->set(LineItemDownloadLoader::class)
        ->args([
            service('product_download.repository'),
        ]);

    $services->set(OrderConverter::class)
        ->args([
            service('customer.repository'),
            service(SalesChannelContextFactory::class),
            service('event_dispatcher'),
            service(NumberRangeValueGeneratorInterface::class),
            service('order_address.repository'),
            service(InitialStateIdLoader::class),
            service(LineItemDownloadLoader::class),
            service('rule.repository'),
        ]);

    $services->set(OrderTransactionStateHandler::class)
        ->args([
            service(StateMachineRegistry::class),
        ]);

    $services->set(OrderTransactionCaptureStateHandler::class)
        ->args([
            service(StateMachineRegistry::class),
        ]);

    $services->set(OrderTransactionCaptureRefundStateHandler::class)
        ->args([
            service(StateMachineRegistry::class),
        ]);

    $services->set(OrderAddressService::class)
        ->args([
            service('order.repository'),
            service('order_address.repository'),
            service('customer_address.repository'),
            service('order_delivery.repository'),
        ]);

    // controller
    $services->set(OrderActionController::class)
        ->public()
        ->args([
            service(OrderService::class),
            service(Connection::class),
            service(PaymentRefundProcessor::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(OrderRoute::class)
        ->public()
        ->args([
            service('order.repository'),
            service('promotion.repository'),
            service('shopware.rate_limiter'),
            service('event_dispatcher'),
            service(AccountService::class),
            service(GuestAuthenticator::class),
            service(ClockInterface::class),
            param('shopware.order.deep_link.expire_days'),
        ]);

    $services->set(CancelOrderRoute::class)
        ->public()
        ->args([
            service(OrderService::class),
            service('order.repository'),
            service(SystemConfigService::class),
        ]);

    $services->set(SetPaymentOrderRoute::class)
        ->public()
        ->args([
            service(OrderService::class),
            service('order.repository'),
            service(OrderConverter::class),
            service(CartRuleLoader::class),
            service(CartService::class),
            service('event_dispatcher'),
            service(InitialStateIdLoader::class),
            service(CheckoutGatewayRoute::class),
        ]);

    // events
    $services->set(OrderStateChangeEventListener::class)
        ->args([
            service('order.repository'),
            service('order_transaction.repository'),
            service('order_delivery.repository'),
            service('event_dispatcher'),
            service(BusinessEventCollector::class),
            service('state_machine_state.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(OrderSalutationSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    // Telemetry: order placed metrics
    $services->set(OrderMetricsSubscriber::class)
        ->args([
            service(Meter::class),
            service(SalesChannelTypeResolver::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('shopware.telemetry.subscriber');
};

<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Cart\Order\OrderConverter;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStateHandler;
use Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\PaymentMethodTranslationDefinition;
use Shopwell\Core\Checkout\Payment\Api\PaymentMethodTechnicalNameFkResolver;
use Shopwell\Core\Checkout\Payment\Cart\PaymentHandler\CashPayment;
use Shopwell\Core\Checkout\Payment\Cart\PaymentHandler\DebitPayment;
use Shopwell\Core\Checkout\Payment\Cart\PaymentHandler\DefaultPayment;
use Shopwell\Core\Checkout\Payment\Cart\PaymentHandler\InvoicePayment;
use Shopwell\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopwell\Core\Checkout\Payment\Cart\PaymentHandler\PrePayment;
use Shopwell\Core\Checkout\Payment\Cart\PaymentMethodValidator as CartPaymentMethodValidator;
use Shopwell\Core\Checkout\Payment\Cart\PaymentRecurringProcessor;
use Shopwell\Core\Checkout\Payment\Cart\PaymentRefundProcessor;
use Shopwell\Core\Checkout\Payment\Cart\PaymentTransactionStructFactory;
use Shopwell\Core\Checkout\Payment\Cart\Token\Constraint\PaymentTokenRegisteredValidator;
use Shopwell\Core\Checkout\Payment\Cart\Token\JWTFactoryV2;
use Shopwell\Core\Checkout\Payment\Cart\Token\PaymentTokenGenerator;
use Shopwell\Core\Checkout\Payment\Cart\Token\PaymentTokenLifecycle;
use Shopwell\Core\Checkout\Payment\Cleanup\CleanupPaymentTokenTask;
use Shopwell\Core\Checkout\Payment\Cleanup\CleanupPaymentTokenTaskHandler;
use Shopwell\Core\Checkout\Payment\Controller\PaymentController;
use Shopwell\Core\Checkout\Payment\DataAbstractionLayer\PaymentDistinguishableNameGenerator;
use Shopwell\Core\Checkout\Payment\DataAbstractionLayer\PaymentDistinguishableNameSubscriber;
use Shopwell\Core\Checkout\Payment\DataAbstractionLayer\PaymentHandlerIdentifierSubscriber;
use Shopwell\Core\Checkout\Payment\DataAbstractionLayer\PaymentMethodIndexer;
use Shopwell\Core\Checkout\Payment\DataAbstractionLayer\PaymentMethodValidator;
use Shopwell\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopwell\Core\Checkout\Payment\PaymentProcessor;
use Shopwell\Core\Checkout\Payment\SalesChannel\HandlePaymentMethodRoute;
use Shopwell\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute;
use Shopwell\Core\Checkout\Payment\SalesChannel\SalesChannelPaymentMethodDefinition;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Rule\RuleIdMatcher;
use Shopwell\Core\Framework\Script\Execution\ScriptExecutor;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(PaymentMethodDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(SalesChannelPaymentMethodDefinition::class)
        ->tag('shopwell.sales_channel.entity.definition');

    $services->set(PaymentMethodTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(PaymentMethodValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(PaymentProcessor::class)
        ->args([
            service(JWTFactoryV2::class)->nullOnInvalid(),
            service(PaymentTokenGenerator::class),
            service(PaymentTokenLifecycle::class),
            service(PaymentHandlerRegistry::class),
            service('order_transaction.repository'),
            service(OrderTransactionStateHandler::class),
            service('logger'),
            service(PaymentTransactionStructFactory::class),
            service(InitialStateIdLoader::class),
            service('router'),
            service(SystemConfigService::class),
        ]);

    $services->set(PaymentController::class)
        ->public()
        ->args([
            service(PaymentProcessor::class),
            service(OrderConverter::class),
            service(JWTFactoryV2::class)->nullOnInvalid(),
            service(PaymentTokenGenerator::class),
            service(PaymentTokenLifecycle::class),
            service('order.repository'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(PaymentTransactionStructFactory::class);

    $services->set(PaymentRefundProcessor::class)
        ->public()
        ->args([
            service(Connection::class),
            service(OrderTransactionCaptureRefundStateHandler::class),
            service(PaymentHandlerRegistry::class),
            service(PaymentTransactionStructFactory::class),
        ]);

    $services->set(PaymentRecurringProcessor::class)
        ->public()
        ->args([
            service('order_transaction.repository'),
            service(InitialStateIdLoader::class),
            service(OrderTransactionStateHandler::class),
            service(PaymentHandlerRegistry::class),
            service(PaymentTransactionStructFactory::class),
            service('logger'),
        ]);

    $services->set(JWTFactoryV2::class)
        ->args([
            service('shopwell.jwt_config'),
            service(Connection::class),
            service(ClockInterface::class),
        ])
        ->tag('shopwell.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(PaymentTokenRegisteredValidator::class)
        ->args([
            service(PaymentTokenLifecycle::class),
        ])
        ->tag('validator.constraint_validator');

    $services->set(PaymentTokenGenerator::class)
        ->args([
            service('shopwell.jwt_config'),
            service(DataValidator::class),
            service(SystemConfigService::class),
        ]);

    $services->set(PaymentTokenLifecycle::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(PaymentHandlerRegistry::class)
        ->args([
            tagged_locator('shopwell.payment.method'),
            service(Connection::class),
        ]);

    $services->set(PrePayment::class)
        ->args([
            service(OrderTransactionStateHandler::class),
        ])
        ->tag('shopwell.payment.method');

    $services->set(DebitPayment::class)
        ->args([
            service(OrderTransactionStateHandler::class),
        ])
        ->tag('shopwell.payment.method')
        ->tag('shopwell.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(CashPayment::class)
        ->args([
            service(OrderTransactionStateHandler::class),
        ])
        ->tag('shopwell.payment.method');

    $services->set(InvoicePayment::class)
        ->args([
            service(OrderTransactionStateHandler::class),
        ])
        ->tag('shopwell.payment.method');

    $services->set(DefaultPayment::class)
        ->args([
            service(OrderTransactionStateHandler::class),
        ])
        ->tag('shopwell.payment.method');

    $services->set(PaymentHandlerIdentifierSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(PaymentDistinguishableNameSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(PaymentMethodIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('event_dispatcher'),
            service('payment_method.repository'),
            service(PaymentDistinguishableNameGenerator::class),
        ])
        ->tag('shopwell.entity_indexer');

    $services->set(PaymentDistinguishableNameGenerator::class)
        ->args([
            service('payment_method.repository'),
        ]);

    $services->set(CartPaymentMethodValidator::class)
        ->tag('shopwell.cart.validator');

    $services->set(CleanupPaymentTokenTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(CleanupPaymentTokenTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(Connection::class),
            service(ClockInterface::class),
        ])
        ->tag('messenger.message_handler');

    // Sales Channel API
    $services->set(PaymentMethodRoute::class)
        ->public()
        ->args([
            service('sales_channel.payment_method.repository'),
            service(CacheTagCollector::class),
            service(ScriptExecutor::class),
            service(RuleIdMatcher::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(HandlePaymentMethodRoute::class)
        ->public()
        ->args([
            service(PaymentProcessor::class),
            service(DataValidator::class),
            service(SalesChannelContextService::class),
            service('currency.repository'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(PaymentMethodTechnicalNameFkResolver::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopwell.sync.fk_resolver');
};

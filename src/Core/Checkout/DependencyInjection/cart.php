<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Cart\Address\AddressValidator;
use Shopwell\Core\Checkout\Cart\CachedRuleLoader;
use Shopwell\Core\Checkout\Cart\Calculator;
use Shopwell\Core\Checkout\Cart\CartCalculator;
use Shopwell\Core\Checkout\Cart\CartCompressor;
use Shopwell\Core\Checkout\Cart\CartContextHasher;
use Shopwell\Core\Checkout\Cart\CartFactory;
use Shopwell\Core\Checkout\Cart\CartLocker;
use Shopwell\Core\Checkout\Cart\CartPersister;
use Shopwell\Core\Checkout\Cart\CartRuleLoader;
use Shopwell\Core\Checkout\Cart\CartSerializationCleaner;
use Shopwell\Core\Checkout\Cart\CartValueResolver;
use Shopwell\Core\Checkout\Cart\Cleanup\CleanupCartTask;
use Shopwell\Core\Checkout\Cart\Cleanup\CleanupCartTaskHandler;
use Shopwell\Core\Checkout\Cart\Command\CartMigrateCommand;
use Shopwell\Core\Checkout\Cart\CreditCartProcessor;
use Shopwell\Core\Checkout\Cart\CustomCartProcessor;
use Shopwell\Core\Checkout\Cart\Delivery\DeliveryBuilder;
use Shopwell\Core\Checkout\Cart\Delivery\DeliveryCalculator;
use Shopwell\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopwell\Core\Checkout\Cart\Delivery\DeliveryValidator;
use Shopwell\Core\Checkout\Cart\Facade\CartFacadeHelper;
use Shopwell\Core\Checkout\Cart\Facade\CartFacadeHookFactory;
use Shopwell\Core\Checkout\Cart\Facade\PriceFactoryFactory;
use Shopwell\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Shopwell\Core\Checkout\Cart\LineItem\Group\AbstractProductLineItemProvider;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemGroupServiceRegistry;
use Shopwell\Core\Checkout\Cart\LineItem\Group\Packager\LineItemGroupCountPackager;
use Shopwell\Core\Checkout\Cart\LineItem\Group\Packager\LineItemGroupUnitPriceGrossPackager;
use Shopwell\Core\Checkout\Cart\LineItem\Group\Packager\LineItemGroupUnitPriceNetPackager;
use Shopwell\Core\Checkout\Cart\LineItem\Group\ProductLineItemProvider;
use Shopwell\Core\Checkout\Cart\LineItem\Group\RulesMatcher\AbstractAnyRuleLineItemMatcher;
use Shopwell\Core\Checkout\Cart\LineItem\Group\RulesMatcher\AnyRuleLineItemMatcher;
use Shopwell\Core\Checkout\Cart\LineItem\Group\RulesMatcher\AnyRuleMatcher;
use Shopwell\Core\Checkout\Cart\LineItem\Group\Sorter\LineItemGroupPriceAscSorter;
use Shopwell\Core\Checkout\Cart\LineItem\Group\Sorter\LineItemGroupPriceDescSorter;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemQuantitySplitter;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemValidator;
use Shopwell\Core\Checkout\Cart\LineItemFactoryHandler\CreditLineItemFactory;
use Shopwell\Core\Checkout\Cart\LineItemFactoryHandler\CustomLineItemFactory;
use Shopwell\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopwell\Core\Checkout\Cart\LineItemFactoryHandler\PromotionLineItemFactory;
use Shopwell\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopwell\Core\Checkout\Cart\Order\Api\OrderConverterController;
use Shopwell\Core\Checkout\Cart\Order\Api\OrderRecalculationController;
use Shopwell\Core\Checkout\Cart\Order\OrderConverter;
use Shopwell\Core\Checkout\Cart\Order\OrderPersister;
use Shopwell\Core\Checkout\Cart\Order\RecalculationService;
use Shopwell\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\AmountCalculator;
use Shopwell\Core\Checkout\Cart\Price\CashRounding;
use Shopwell\Core\Checkout\Cart\Price\CurrencyPriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopwell\Core\Checkout\Cart\PriceActionController;
use Shopwell\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopwell\Core\Checkout\Cart\Processor;
use Shopwell\Core\Checkout\Cart\Processor\ContainerCartProcessor;
use Shopwell\Core\Checkout\Cart\Processor\DiscountCartProcessor;
use Shopwell\Core\Checkout\Cart\RedisCartPersister;
use Shopwell\Core\Checkout\Cart\RuleLoader;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartDeleteRoute;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartItemAddRoute;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartItemRemoveRoute;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartItemUpdateRoute;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartLoadRoute;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartOrderRoute;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartService;
use Shopwell\Core\Checkout\Cart\SalesChannel\ProductShippingCostRoute;
use Shopwell\Core\Checkout\Cart\SalesChannel\ShippingCostRoute;
use Shopwell\Core\Checkout\Cart\Subscriber\CartOrderEventSubscriber;
use Shopwell\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopwell\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopwell\Core\Checkout\Cart\Tax\TaxDetector;
use Shopwell\Core\Checkout\Cart\TaxProvider\TaxAdjustment;
use Shopwell\Core\Checkout\Cart\TaxProvider\TaxAdjustmentCalculator;
use Shopwell\Core\Checkout\Cart\TaxProvider\TaxProviderProcessor;
use Shopwell\Core\Checkout\Cart\TaxProvider\TaxProviderRegistry;
use Shopwell\Core\Checkout\Cart\Telemetry\CartMetricsInstrumentor;
use Shopwell\Core\Checkout\Cart\Transaction\TransactionProcessor;
use Shopwell\Core\Checkout\Cart\Validator;
use Shopwell\Core\Checkout\Gateway\Command\Executor\CheckoutGatewayCommandExecutor;
use Shopwell\Core\Checkout\Gateway\Command\Handler\AddCartErrorCommandHandler;
use Shopwell\Core\Checkout\Gateway\Command\Handler\AddPaymentMethodCommandHandler;
use Shopwell\Core\Checkout\Gateway\Command\Handler\AddPaymentMethodExtensionsCommandHandler;
use Shopwell\Core\Checkout\Gateway\Command\Handler\AddShippingMethodCommandHandler;
use Shopwell\Core\Checkout\Gateway\Command\Handler\AddShippingMethodExtensionsCommandHandler;
use Shopwell\Core\Checkout\Gateway\Command\Handler\RemovePaymentMethodCommandHandler;
use Shopwell\Core\Checkout\Gateway\Command\Handler\RemoveShippingMethodCommandHandler;
use Shopwell\Core\Checkout\Gateway\Command\Registry\CheckoutGatewayCommandRegistry;
use Shopwell\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRoute;
use Shopwell\Core\Checkout\Order\OrderAddressService;
use Shopwell\Core\Checkout\Payment\PaymentProcessor;
use Shopwell\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute;
use Shopwell\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopwell\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopwell\Core\Content\Product\Cart\ProductCartProcessor;
use Shopwell\Core\Content\Product\Cart\ProductFeatureBuilder;
use Shopwell\Core\Content\Product\Cart\ProductGateway;
use Shopwell\Core\Content\Product\Cart\ProductLineItemValidator;
use Shopwell\Core\Content\Product\ProductTypeRegistry;
use Shopwell\Core\Content\Product\SalesChannel\Price\ProductPriceCalculator;
use Shopwell\Core\Framework\Adapter\Cache\RedisConnectionFactory;
use Shopwell\Core\Framework\Adapter\Redis\RedisConnectionProvider;
use Shopwell\Core\Framework\Adapter\Translation\Translator;
use Shopwell\Core\Framework\App\Checkout\Gateway\AppCheckoutGateway;
use Shopwell\Core\Framework\App\Privileges\AppCapability;
use Shopwell\Core\Framework\App\TaxProvider\Payload\TaxProviderPayloadService;
use Shopwell\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\ExceptionLogger;
use Shopwell\Core\Framework\Script\Execution\ScriptExecutor;
use Shopwell\Core\Framework\Telemetry\Metrics\Meter;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopwell\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopwell\Core\System\SalesChannel\Telemetry\SalesChannelTypeResolver;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CreditCartProcessor::class)
        ->args([
            service(AbsolutePriceCalculator::class),
        ])
        ->tag('shopwell.cart.processor');

    $services->set(CustomCartProcessor::class)
        ->args([
            service(QuantityPriceCalculator::class),
        ])
        ->tag('shopwell.cart.processor', ['priority' => 4000])
        ->tag('shopwell.cart.collector');

    $services->set(CartValueResolver::class)
        ->args([
            service(CartService::class),
        ])
        ->tag('controller.argument_value_resolver', ['priority' => 1001]);

    // Price calculation
    $services->set(AmountCalculator::class)
        ->args([
            service(CashRounding::class),
            service(PercentageTaxRuleBuilder::class),
            service(TaxCalculator::class),
        ]);

    $services->set(CleanupCartTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(CleanupCartTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(CartPersister::class),
            param('shopwell.cart.expire_days'),
        ])
        ->tag('messenger.message_handler');

    $services->set(CashRounding::class);

    $services->set(CartPersister::class)
        ->args([
            service(Connection::class),
            service('event_dispatcher'),
            service(CartSerializationCleaner::class),
            service(CartCompressor::class),
            service(ClockInterface::class),
        ]);

    $services->set(CartLocker::class)
        ->args([
            service('lock.factory'),
        ]);

    $services->set(CartSerializationCleaner::class)
        ->args([
            service(Connection::class),
            service('event_dispatcher'),
        ]);

    $services->set(CartService::class)
        ->public()
        ->lazy()
        ->args([
            service(CartPersister::class),
            service('event_dispatcher'),
            service(CartCalculator::class),
            service(CartLoadRoute::class),
            service(CartDeleteRoute::class),
            service(CartItemAddRoute::class),
            service(CartItemUpdateRoute::class),
            service(CartItemRemoveRoute::class),
            service(CartOrderRoute::class),
            service(CartFactory::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(CartCalculator::class)
        ->args([
            service(CartRuleLoader::class),
            service(CartContextHasher::class),
            service(CartMetricsInstrumentor::class),
        ]);

    // Telemetry: cart calculation metrics collaborator
    $services->set(CartMetricsInstrumentor::class)
        ->args([
            service(Meter::class),
            service(SalesChannelTypeResolver::class),
        ]);

    $services->set(CartFactory::class)
        ->args([
            service('event_dispatcher'),
        ]);

    $services->set(CartItemUpdateRoute::class)
        ->public()
        ->args([
            service(CartPersister::class),
            service(CartCalculator::class),
            service(LineItemFactoryRegistry::class),
            service('event_dispatcher'),
            service(CartLocker::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(CartLoadRoute::class)
        ->public()
        ->args([
            service(CartCalculator::class),
            service(TaxProviderProcessor::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(CartDeleteRoute::class)
        ->public()
        ->args([
            service(CartPersister::class),
            service('event_dispatcher'),
            service(CartLocker::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(CartItemRemoveRoute::class)
        ->public()
        ->args([
            service('event_dispatcher'),
            service(CartCalculator::class),
            service(CartPersister::class),
            service(CartLocker::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(CartItemAddRoute::class)
        ->public()
        ->args([
            service(CartCalculator::class),
            service(CartPersister::class),
            service('event_dispatcher'),
            service(LineItemFactoryRegistry::class),
            service('shopwell.rate_limiter'),
            service(CartLocker::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(CartOrderRoute::class)
        ->public()
        ->args([
            service(CartCalculator::class),
            service('order.repository'),
            service(OrderPersister::class),
            service(CartPersister::class),
            service('event_dispatcher'),
            service(PaymentProcessor::class),
            service(TaxProviderProcessor::class),
            service(CheckoutGatewayRoute::class),
            service(CartContextHasher::class),
            service(ExtensionDispatcher::class),
            service(CartLocker::class),
        ]);

    $services->set(ShippingCostRoute::class)
        ->public()
        ->args([
            service('shipping_method.repository'),
            service(CartRuleLoader::class),
            service(CheckoutGatewayRoute::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ProductShippingCostRoute::class)
        ->public()
        ->args([
            service(ProductGateway::class),
            service('shipping_method.repository'),
            service(Processor::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(QuantityPriceCalculator::class)
        ->args([
            service(GrossPriceCalculator::class),
            service(NetPriceCalculator::class),
        ]);

    $services->set(GrossPriceCalculator::class)
        ->args([
            service(TaxCalculator::class),
            service(CashRounding::class),
        ]);

    $services->set(NetPriceCalculator::class)
        ->args([
            service(TaxCalculator::class),
            service(CashRounding::class),
        ]);

    $services->set(PercentagePriceCalculator::class)
        ->args([
            service(CashRounding::class),
            service(QuantityPriceCalculator::class),
            service(PercentageTaxRuleBuilder::class),
        ]);

    $services->set(AbsolutePriceCalculator::class)
        ->args([
            service(QuantityPriceCalculator::class),
            service(PercentageTaxRuleBuilder::class),
        ]);

    $services->set(CurrencyPriceCalculator::class)
        ->args([
            service(QuantityPriceCalculator::class),
            service(PercentageTaxRuleBuilder::class),
        ]);

    $services->set(CartContextHasher::class)
        ->args([
            service('event_dispatcher'),
        ]);

    // Tax calculation
    $services->set(PercentageTaxRuleBuilder::class);

    $services->set(TaxDetector::class);

    $services->set(TaxCalculator::class);

    // Tax providers
    $services->set(TaxProviderProcessor::class)
        ->args([
            service('tax_provider.repository'),
            service('logger'),
            service(TaxAdjustment::class),
            service(TaxProviderRegistry::class),
            service(TaxProviderPayloadService::class),
            service(AppCapability::class),
        ]);

    $services->set(TaxProviderRegistry::class)
        ->public()
        ->args([
            tagged_iterator('shopwell.tax.provider'),
        ]);

    $services->set(TaxAdjustmentCalculator::class);

    $services->set('shopwell.tax.adjustment_calculator', AmountCalculator::class)
        ->args([
            service(CashRounding::class),
            service(PercentageTaxRuleBuilder::class),
            service(TaxAdjustmentCalculator::class),
        ]);

    $services->set(TaxAdjustment::class)
        ->args([
            service('shopwell.tax.adjustment_calculator'),
            service(CashRounding::class),
            service(TransactionProcessor::class),
        ]);

    // Checkout gateway
    $services->set(CheckoutGatewayRoute::class)
        ->public()
        ->args([
            service(PaymentMethodRoute::class),
            service(ShippingMethodRoute::class),
            service(AppCheckoutGateway::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(CheckoutGatewayCommandRegistry::class)
        ->args([
            tagged_iterator('shopwell.checkout.gateway.command'),
        ]);

    $services->set(CheckoutGatewayCommandExecutor::class)
        ->args([
            service(CheckoutGatewayCommandRegistry::class),
            service(ExceptionLogger::class),
        ]);

    $services->set(AddCartErrorCommandHandler::class)
        ->tag('shopwell.checkout.gateway.command');

    $services->set(AddPaymentMethodCommandHandler::class)
        ->args([
            service('payment_method.repository'),
            service(ExceptionLogger::class),
        ])
        ->tag('shopwell.checkout.gateway.command');

    $services->set(AddPaymentMethodExtensionsCommandHandler::class)
        ->args([
            service(ExceptionLogger::class),
        ])
        ->tag('shopwell.checkout.gateway.command');

    $services->set(RemovePaymentMethodCommandHandler::class)
        ->tag('shopwell.checkout.gateway.command');

    $services->set(AddShippingMethodCommandHandler::class)
        ->args([
            service('payment_method.repository'),
            service(ExceptionLogger::class),
        ])
        ->tag('shopwell.checkout.gateway.command');

    $services->set(AddShippingMethodExtensionsCommandHandler::class)
        ->args([
            service(ExceptionLogger::class),
        ])
        ->tag('shopwell.checkout.gateway.command');

    $services->set(RemoveShippingMethodCommandHandler::class)
        ->tag('shopwell.checkout.gateway.command');

    $services->set(DeliveryBuilder::class);

    $services->set(DeliveryCalculator::class)
        ->args([
            service(QuantityPriceCalculator::class),
            service(PercentageTaxRuleBuilder::class),
            service(CashRounding::class),
        ]);

    $services->set(PriceActionController::class)
        ->public()
        ->args([
            service('tax.repository'),
            service(NetPriceCalculator::class),
            service(GrossPriceCalculator::class),
            service('currency.repository'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(Calculator::class)
        ->public()
        ->args([
            service(QuantityPriceCalculator::class),
            service(PercentagePriceCalculator::class),
            service(AbsolutePriceCalculator::class),
        ]);

    $services->set(DeliveryProcessor::class)
        ->args([
            service(DeliveryBuilder::class),
            service(DeliveryCalculator::class),
            service('shipping_method.repository'),
        ])
        ->tag('shopwell.cart.processor', ['priority' => -5000])
        ->tag('shopwell.cart.collector', ['priority' => -5000]);

    $services->set(DeliveryValidator::class)
        ->tag('shopwell.cart.validator');

    $services->set(LineItemValidator::class)
        ->tag('shopwell.cart.validator');

    $services->set(AddressValidator::class)
        ->args([
            service('sales_channel_country.repository'),
        ])
        ->tag('shopwell.cart.validator')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(Validator::class)
        ->args([
            tagged_iterator('shopwell.cart.validator'),
        ]);

    $services->set(ProductLineItemValidator::class)
        ->tag('shopwell.cart.validator');

    $services->set(Processor::class)
        ->args([
            service(Validator::class),
            service(AmountCalculator::class),
            service(TransactionProcessor::class),
            tagged_iterator('shopwell.cart.processor'),
            tagged_iterator('shopwell.cart.collector'),
            service(ScriptExecutor::class),
        ]);

    $services->set(ProductCartProcessor::class)
        ->args([
            service(ProductGateway::class),
            service(QuantityPriceCalculator::class),
            service(ProductFeatureBuilder::class),
            service(ProductPriceCalculator::class),
            service(EntityCacheKeyGenerator::class),
            service(Connection::class),
            service(ProductTypeRegistry::class),
        ])
        ->tag('shopwell.cart.processor', ['priority' => 5000])
        ->tag('shopwell.cart.collector', ['priority' => 5000]);

    $services->set(ProductFeatureBuilder::class)
        ->args([
            service('custom_field.repository'),
            service(LanguageLocaleCodeProvider::class),
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(TransactionProcessor::class);

    $services->set(OrderConverterController::class)
        ->public()
        ->args([
            service(OrderConverter::class),
            service(CartPersister::class),
            service('order.repository'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(OrderRecalculationController::class)
        ->public()
        ->args([
            service(RecalculationService::class),
            service(OrderAddressService::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(RecalculationService::class)
        ->args([
            service('order.repository'),
            service(OrderConverter::class),
            service(CartService::class),
            service('product.repository'),
            service('order_address.repository'),
            service('customer_address.repository'),
            service('order_line_item.repository'),
            service('order_delivery.repository'),
            service(Processor::class),
            service(CartRuleLoader::class),
            service(PromotionItemBuilder::class),
            service(LineItemFactoryRegistry::class),
        ]);

    $services->set(CartRuleLoader::class)
        ->args([
            service(CartPersister::class),
            service(Processor::class),
            service('logger'),
            service('cache.object'),
            service(RuleLoader::class),
            service(TaxDetector::class),
            service(Connection::class),
            service(CartFactory::class),
            service(ExtensionDispatcher::class),
            service(Translator::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(CachedRuleLoader::class)
        ->decorate(RuleLoader::class, null, -1000)
        ->args([
            service(CachedRuleLoader::class . '.inner'),
            service('cache.object'),
        ]);

    $services->set(RuleLoader::class)
        ->args([
            service('rule.repository'),
        ]);

    $services->set(LineItemQuantitySplitter::class)
        ->args([
            service(QuantityPriceCalculator::class),
        ]);

    $services->set(PriceDefinitionFactory::class);

    $services->set(LineItemFactoryRegistry::class)
        ->args([
            tagged_iterator('shopwell.cart.line_item.factory'),
            service(DataValidator::class),
            service('event_dispatcher'),
        ]);

    $services->set(ProductLineItemFactory::class)
        ->args([
            service(PriceDefinitionFactory::class),
        ])
        ->tag('shopwell.cart.line_item.factory');

    $services->set(PromotionLineItemFactory::class)
        ->tag('shopwell.cart.line_item.factory');

    $services->set(CreditLineItemFactory::class)
        ->args([
            service(PriceDefinitionFactory::class),
            service('media.repository'),
        ])
        ->tag('shopwell.cart.line_item.factory');

    $services->set(CustomLineItemFactory::class)
        ->args([
            service(PriceDefinitionFactory::class),
            service('media.repository'),
        ])
        ->tag('shopwell.cart.line_item.factory');

    // Line item groups
    $services->set(AbstractAnyRuleLineItemMatcher::class, AnyRuleLineItemMatcher::class);

    $services->set(AbstractProductLineItemProvider::class, ProductLineItemProvider::class);

    $services->set(LineItemGroupBuilder::class)
        ->args([
            service(LineItemGroupServiceRegistry::class),
            service(AnyRuleMatcher::class),
            service(LineItemQuantitySplitter::class),
            service(AbstractProductLineItemProvider::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(LineItemGroupServiceRegistry::class)
        ->args([
            tagged_iterator('lineitem.group.packager'),
            tagged_iterator('lineitem.group.sorter'),
        ]);

    $services->set(LineItemGroupCountPackager::class)
        ->tag('lineitem.group.packager');

    $services->set(LineItemGroupUnitPriceGrossPackager::class)
        ->tag('lineitem.group.packager');

    $services->set(LineItemGroupUnitPriceNetPackager::class)
        ->tag('lineitem.group.packager');

    $services->set(LineItemGroupPriceAscSorter::class)
        ->tag('lineitem.group.sorter');

    $services->set(LineItemGroupPriceDescSorter::class)
        ->tag('lineitem.group.sorter');

    $services->set(AnyRuleMatcher::class)
        ->args([
            service(AbstractAnyRuleLineItemMatcher::class),
        ]);

    $services->set(CartFacadeHookFactory::class)
        ->public()
        ->args([
            service(CartFacadeHelper::class),
            service(ScriptPriceStubs::class),
        ]);

    $services->set(PriceFactoryFactory::class)
        ->public()
        ->args([
            service(ScriptPriceStubs::class),
        ]);

    $services->set(ScriptPriceStubs::class)
        ->args([
            service(Connection::class),
            service(QuantityPriceCalculator::class),
            service(PercentagePriceCalculator::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(CartFacadeHelper::class)
        ->args([
            service(LineItemFactoryRegistry::class),
            service(Processor::class),
            service(ScriptPriceStubs::class),
        ]);

    $services->set(ContainerCartProcessor::class)
        ->tag('shopwell.cart.processor', ['priority' => 3800])
        ->args([
            service(PercentagePriceCalculator::class),
            service(QuantityPriceCalculator::class),
            service(CurrencyPriceCalculator::class),
        ]);

    $services->set(DiscountCartProcessor::class)
        ->tag('shopwell.cart.processor', ['priority' => 3700])
        ->args([
            service(PercentagePriceCalculator::class),
            service(CurrencyPriceCalculator::class),
        ]);

    $services->set(CartCompressor::class)
        ->args([
            param('shopwell.cart.compress'),
            param('shopwell.cart.compression_method'),
            param('shopwell.cart.serialization_max_mb_size'),
        ]);

    $services->set(RedisCartPersister::class)
        ->args([
            service('shopwell.cart.redis'),
            service('event_dispatcher'),
            service(CartSerializationCleaner::class),
            service(CartCompressor::class),
            param('shopwell.cart.expire_days'),
        ]);

    $services->set('shopwell.cart.redis', \Redis::class)
        ->factory([service(RedisConnectionProvider::class), 'getConnection'])
        ->args([
            param('shopwell.cart.storage.config.connection'),
        ]);

    $services->set(CartMigrateCommand::class)
        ->args([
            service('shopwell.cart.redis')->nullOnInvalid(),
            service(Connection::class),
            param('shopwell.cart.expire_days'),
            service(RedisConnectionFactory::class),
            service(CartCompressor::class),
            service(ClockInterface::class),
        ])
        ->tag('console.command');

    $services->set(CartOrderEventSubscriber::class)
        ->args([
            service(ContextSwitchRoute::class),
            service(LineItemGroupBuilder::class),
        ])
        ->tag('kernel.event_subscriber');
};

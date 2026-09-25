<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemGroupServiceRegistry;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemQuantitySplitter;
use Shopwell\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\AmountCalculator;
use Shopwell\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopwell\Core\Checkout\Promotion\Aggregate\PromotionCartRule\PromotionCartRuleDefinition;
use Shopwell\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountDefinition;
use Shopwell\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice\PromotionDiscountPriceDefinition;
use Shopwell\Core\Checkout\Promotion\Aggregate\PromotionDiscountRule\PromotionDiscountRuleDefinition;
use Shopwell\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeDefinition;
use Shopwell\Core\Checkout\Promotion\Aggregate\PromotionOrderRule\PromotionOrderRuleDefinition;
use Shopwell\Core\Checkout\Promotion\Aggregate\PromotionPersonaCustomer\PromotionPersonaCustomerDefinition;
use Shopwell\Core\Checkout\Promotion\Aggregate\PromotionPersonaRule\PromotionPersonaRuleDefinition;
use Shopwell\Core\Checkout\Promotion\Aggregate\PromotionSalesChannel\PromotionSalesChannelDefinition;
use Shopwell\Core\Checkout\Promotion\Aggregate\PromotionSetGroup\PromotionSetGroupDefinition;
use Shopwell\Core\Checkout\Promotion\Aggregate\PromotionSetGroupRule\PromotionSetGroupRuleDefinition;
use Shopwell\Core\Checkout\Promotion\Aggregate\PromotionTranslation\PromotionTranslationDefinition;
use Shopwell\Core\Checkout\Promotion\Api\PromotionActionController;
use Shopwell\Core\Checkout\Promotion\Api\PromotionController;
use Shopwell\Core\Checkout\Promotion\Cart\CartPromotionsSubscriber;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionBuilder;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\Filter\AdvancedPackageFilter;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\Filter\AdvancedPackagePicker;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\Filter\AdvancedPackageRules;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\Filter\FilterServiceRegistry;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\Filter\Picker\HorizontalPicker;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\Filter\Picker\VerticalPicker;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\Filter\Sorter\FilterSorterPriceAsc;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\Filter\Sorter\FilterSorterPriceDesc;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\ScopePackager\CartScopeDiscountPackager;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\ScopePackager\SetGroupScopeDiscountPackager;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\ScopePackager\SetScopeDiscountPackager;
use Shopwell\Core\Checkout\Promotion\Cart\PromotionCalculator;
use Shopwell\Core\Checkout\Promotion\Cart\PromotionCollector;
use Shopwell\Core\Checkout\Promotion\Cart\PromotionDeliveryCalculator;
use Shopwell\Core\Checkout\Promotion\Cart\PromotionDeliveryProcessor;
use Shopwell\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopwell\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopwell\Core\Checkout\Promotion\Cart\PromotionRedemptionLocker;
use Shopwell\Core\Checkout\Promotion\DataAbstractionLayer\PromotionExclusionUpdater;
use Shopwell\Core\Checkout\Promotion\DataAbstractionLayer\PromotionIndexer;
use Shopwell\Core\Checkout\Promotion\DataAbstractionLayer\PromotionRedemptionUpdater;
use Shopwell\Core\Checkout\Promotion\DataAbstractionLayer\PromotionValidator as PromotionDalValidator;
use Shopwell\Core\Checkout\Promotion\Gateway\PromotionGateway;
use Shopwell\Core\Checkout\Promotion\PromotionDefinition;
use Shopwell\Core\Checkout\Promotion\Service\PromotionDateTimeService;
use Shopwell\Core\Checkout\Promotion\Subscriber\PromotionIndividualCodeRedeemer;
use Shopwell\Core\Checkout\Promotion\Subscriber\Storefront\StorefrontCartSubscriber;
use Shopwell\Core\Checkout\Promotion\Util\PromotionCodeService;
use Shopwell\Core\Checkout\Promotion\Validator\PromotionValidator;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopwell\Core\Framework\Util\HtmlSanitizer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // DAL DEFINITIONS + SERVICES
    $services->set(PromotionDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(PromotionSalesChannelDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(PromotionIndividualCodeDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(PromotionDiscountDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(PromotionDiscountRuleDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(PromotionSetGroupDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(PromotionSetGroupRuleDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(PromotionOrderRuleDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(PromotionPersonaCustomerDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(PromotionPersonaRuleDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(PromotionCartRuleDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(PromotionTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(PromotionDiscountPriceDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(PromotionValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(PromotionRedemptionLocker::class)
        ->args([
            service('lock.factory'),
        ])
        ->tag('kernel.event_subscriber');

    // CART CALCULATORS
    $services->set(PromotionItemBuilder::class);

    $services->set(PromotionCollector::class)
        ->args([
            service(PromotionGateway::class),
            service(PromotionItemBuilder::class),
            service(HtmlSanitizer::class),
            service(Connection::class),
        ])
        ->tag('shopwell.cart.collector', ['priority' => 4900]);

    $services->set(PromotionProcessor::class)
        ->args([
            service(PromotionCalculator::class),
            service(LineItemGroupBuilder::class),
        ])
        ->tag('shopwell.cart.processor', ['priority' => 4900]);

    $services->set(PromotionDeliveryProcessor::class)
        ->args([
            service(PromotionDeliveryCalculator::class),
            service(LineItemGroupBuilder::class),
        ])
        ->tag('shopwell.cart.processor', ['priority' => -5100]);

    $services->set(PromotionCalculator::class)
        ->args([
            service(AmountCalculator::class),
            service(AbsolutePriceCalculator::class),
            service(LineItemGroupBuilder::class),
            service(DiscountCompositionBuilder::class),
            service(AdvancedPackageFilter::class),
            service(AdvancedPackagePicker::class),
            service(AdvancedPackageRules::class),
            service(LineItemQuantitySplitter::class),
            service(PercentagePriceCalculator::class),
            service(CartScopeDiscountPackager::class),
            service(SetGroupScopeDiscountPackager::class),
            service(SetScopeDiscountPackager::class),
        ]);

    $services->set(PromotionDeliveryCalculator::class)
        ->args([
            service(QuantityPriceCalculator::class),
            service(PercentagePriceCalculator::class),
            service(PromotionItemBuilder::class),
        ]);

    // SUBSCRIBERS
    $services->set(StorefrontCartSubscriber::class)
        ->args([
            service('event_dispatcher'),
            service('request_stack'),
        ])
        ->tag('kernel.event_subscriber');

    // API CONTROLLERS
    $services->set(PromotionActionController::class)
        ->public()
        ->args([
            service(LineItemGroupServiceRegistry::class),
            service(FilterServiceRegistry::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(PromotionController::class)
        ->public()
        ->args([
            service(PromotionCodeService::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    // FILTER SERVICES
    $services->set(AdvancedPackageFilter::class)
        ->args([
            service(FilterServiceRegistry::class),
        ]);

    $services->set(AdvancedPackagePicker::class)
        ->args([
            service(FilterServiceRegistry::class),
        ]);

    $services->set(AdvancedPackageRules::class);

    $services->set(FilterServiceRegistry::class)
        ->args([
            tagged_iterator('promotion.filter.sorter'),
            tagged_iterator('promotion.filter.picker'),
        ]);

    $services->set(FilterSorterPriceAsc::class)
        ->tag('promotion.filter.sorter');

    $services->set(FilterSorterPriceDesc::class)
        ->tag('promotion.filter.sorter');

    $services->set(VerticalPicker::class)
        ->tag('promotion.filter.picker');

    $services->set(HorizontalPicker::class)
        ->tag('promotion.filter.picker');

    // ADDITIONAL SERVICES
    $services->set(PromotionGateway::class)
        ->args([
            service('promotion.repository'),
            service(PromotionDateTimeService::class),
        ]);

    $services->set(PromotionIndividualCodeRedeemer::class)
        ->args([
            service('promotion_individual_code.repository'),
            service('order_customer.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(PromotionDateTimeService::class);

    $services->set(PromotionCodeService::class)
        ->args([
            service('promotion.repository'),
            service('promotion_individual_code.repository'),
            service(Connection::class),
        ]);

    $services->set(DiscountCompositionBuilder::class);

    $services->set(PromotionIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('promotion.repository'),
            service(PromotionExclusionUpdater::class),
            service(PromotionRedemptionUpdater::class),
            service('event_dispatcher'),
        ])
        ->tag('shopwell.entity_indexer');

    $services->set(PromotionRedemptionUpdater::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(PromotionExclusionUpdater::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(PromotionDalValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CartScopeDiscountPackager::class);

    $services->set(SetGroupScopeDiscountPackager::class)
        ->args([
            service(LineItemGroupBuilder::class),
        ]);

    $services->set(SetScopeDiscountPackager::class)
        ->args([
            service(LineItemGroupBuilder::class),
        ]);

    $services->set(CartPromotionsSubscriber::class)
        ->tag('kernel.event_subscriber');
};

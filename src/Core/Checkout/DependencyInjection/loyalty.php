<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Cart\CartLocker;
use Shopwell\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartService;
use Shopwell\Core\Checkout\Loyalty\Api\LoyaltyController;
use Shopwell\Core\Checkout\Loyalty\Cart\LoyaltyPointCartProcessor;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyDefinition;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerPointTransaction\CustomerPointTransactionDefinition;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\Aggregate\LoyaltyTierTranslation\LoyaltyTierTranslationDefinition;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierDefinition;
use Shopwell\Core\Checkout\Loyalty\Entity\MembershipOrder\MembershipOrderDefinition;
use Shopwell\Core\Checkout\Loyalty\Entity\MembershipPlan\Aggregate\MembershipPlanTranslation\MembershipPlanTranslationDefinition;
use Shopwell\Core\Checkout\Loyalty\Entity\MembershipPlan\MembershipPlanDefinition;
use Shopwell\Core\Checkout\Loyalty\SalesChannel\LoyaltyRedeemRoute;
use Shopwell\Core\Checkout\Loyalty\SalesChannel\LoyaltyRoute;
use Shopwell\Core\Checkout\Loyalty\SalesChannel\MembershipPurchaseRoute;
use Shopwell\Core\Checkout\Loyalty\ScheduledTask\LoyaltyPointExpirationTask;
use Shopwell\Core\Checkout\Loyalty\ScheduledTask\LoyaltyPointExpirationTaskHandler;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyLedger;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyPointService;
use Shopwell\Core\Checkout\Loyalty\Service\MembershipService;
use Shopwell\Core\Checkout\Loyalty\Service\TierResolver;
use Shopwell\Core\Checkout\Loyalty\Subscriber\LoyaltyMembershipProvisionSubscriber;
use Shopwell\Core\Checkout\Loyalty\Subscriber\MembershipOrderSettlementSubscriber;
use Shopwell\Core\Checkout\Loyalty\Subscriber\OrderStateChangeLoyaltySubscriber;
use Shopwell\Core\Checkout\Payment\Storefront\StorefrontPaymentService;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CustomerLoyaltyDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(CustomerPointTransactionDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(LoyaltyTierDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(LoyaltyTierTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(MembershipPlanDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(MembershipPlanTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(MembershipOrderDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(LoyaltyLedger::class)
        ->args([
            service('customer_loyalty.repository'),
            service('customer_point_transaction.repository'),
            service(Connection::class),
            service(ClockInterface::class),
        ]);

    $services->set(MembershipService::class)
        ->args([
            service('membership_plan.repository'),
            service('membership_order.repository'),
            service(LoyaltyLedger::class),
            service(StorefrontPaymentService::class),
            service(Connection::class),
            service(ClockInterface::class),
        ]);

    $services->set(MembershipOrderSettlementSubscriber::class)
        ->args([
            service(MembershipService::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(LoyaltyPointService::class)
        ->args([
            service('customer_loyalty.repository'),
            service(LoyaltyLedger::class),
            service(SystemConfigService::class),
            service('event_dispatcher'),
            service(ClockInterface::class),
            service(TierResolver::class),
        ]);

    $services->set(TierResolver::class)
        ->args([
            service('loyalty_tier.repository'),
            service('customer_loyalty.repository'),
            service('customer.repository'),
            service(SystemConfigService::class),
            service('event_dispatcher'),
            service(ClockInterface::class),
        ]);

    $services->set(LoyaltyMembershipProvisionSubscriber::class)
        ->args([
            service(LoyaltyPointService::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(OrderStateChangeLoyaltySubscriber::class)
        ->args([
            service('order.repository'),
            service(LoyaltyPointService::class),
            service(TierResolver::class),
            service(SystemConfigService::class),
        ])
        ->tag('kernel.event_subscriber');

    // runs after the credit processor (priority 0) so the derived line items are re-clamped on every calculation
    $services->set(LoyaltyPointCartProcessor::class)
        ->args([
            service(AbsolutePriceCalculator::class),
            service(SystemConfigService::class),
            service(LoyaltyPointService::class),
        ])
        ->tag('shopwell.cart.processor', ['priority' => -1000]);

    $services->set(LoyaltyController::class)
        ->public()
        ->args([
            service('customer_loyalty.repository'),
            service('customer_point_transaction.repository'),
            service('loyalty_tier.repository'),
            service(LoyaltyPointService::class),
            service(LoyaltyLedger::class),
        ]);

    $services->set(LoyaltyRoute::class)
        ->public()
        ->args([
            service('customer_loyalty.repository'),
            service('loyalty_tier.repository'),
            service(SystemConfigService::class),
            service(MembershipService::class),
            service(ClockInterface::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(MembershipPurchaseRoute::class)
        ->public()
        ->args([
            service(MembershipService::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(LoyaltyRedeemRoute::class)
        ->public()
        ->args([
            service(LoyaltyPointService::class),
            service(SystemConfigService::class),
            service(CartService::class),
            service(CartLocker::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(LoyaltyPointExpirationTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(LoyaltyPointExpirationTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service('customer_point_transaction.repository'),
            service(LoyaltyPointService::class),
            service(ClockInterface::class),
        ])
        ->tag('messenger.message_handler');
};

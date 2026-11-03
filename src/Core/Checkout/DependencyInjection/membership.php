<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Ledger\RowLock;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyAccountService;
use Shopwell\Core\Checkout\Loyalty\Service\TierResolver;
use Shopwell\Core\Checkout\Membership\Entity\MembershipPlan\Aggregate\MembershipPlanTranslation\MembershipPlanTranslationDefinition;
use Shopwell\Core\Checkout\Membership\Entity\MembershipPlan\MembershipPlanDefinition;
use Shopwell\Core\Checkout\Membership\Entity\MembershipPurchase\MembershipPurchaseDefinition;
use Shopwell\Core\Checkout\Membership\Entity\MembershipSubscription\MembershipSubscriptionDefinition;
use Shopwell\Core\Checkout\Membership\Purchase\MembershipPurchaseStore;
use Shopwell\Core\Checkout\Membership\SalesChannel\MembershipPresenter;
use Shopwell\Core\Checkout\Membership\SalesChannel\MembershipPurchaseRoute;
use Shopwell\Core\Checkout\Membership\SalesChannel\MembershipRoute;
use Shopwell\Core\Checkout\Membership\ScheduledTask\MembershipExpirationTask;
use Shopwell\Core\Checkout\Membership\ScheduledTask\MembershipExpirationTaskHandler;
use Shopwell\Core\Checkout\Membership\Service\MembershipOfferService;
use Shopwell\Core\Checkout\Membership\Service\MembershipPlanService;
use Shopwell\Core\Checkout\Membership\Service\MembershipPurchaseService;
use Shopwell\Core\Checkout\Membership\Service\MembershipSubscriptionService;
use Shopwell\Core\Checkout\Membership\Subscriber\MembershipPurchaseSettlementSubscriber;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseAttach;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseSettlement;
use Shopwell\Core\Checkout\Payment\Storefront\StorefrontPaymentService;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(MembershipPlanDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(MembershipPlanTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(MembershipPurchaseDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(MembershipSubscriptionDefinition::class)
        ->tag('shopwell.entity.definition');

    // the marker a membership order carries on its payment order, built and read through one object
    $services->set('shopwell.membership.purchase_attach', PurchaseAttach::class)
        ->args([
            'membership_purchase:',
        ]);

    $services->set(MembershipPlanService::class)
        ->args([
            service('membership_plan.repository'),
        ]);

    $services->set(MembershipPresenter::class);

    $services->set(MembershipPurchaseService::class)
        ->args([
            service('membership_purchase.repository'),
            service(MembershipPlanService::class),
            service(StorefrontPaymentService::class),
            service('shopwell.membership.purchase_attach'),
        ]);

    $services->set(MembershipOfferService::class)
        ->args([
            service(MembershipPlanService::class),
            service(MembershipPurchaseService::class),
            service(MembershipPresenter::class),
            service(StorefrontPaymentService::class),
        ]);

    $services->set(MembershipSubscriptionService::class)
        ->args([
            service('membership_subscription.repository'),
            service('loyalty_account.repository'),
            service(LoyaltyAccountService::class),
            service(TierResolver::class),
            service(RowLock::class),
            service(Connection::class),
            service(ClockInterface::class),
        ]);

    $services->set(MembershipPurchaseStore::class)
        ->args([
            service('membership_purchase.repository'),
            service(MembershipSubscriptionService::class),
            service(RowLock::class),
        ]);

    $services->set(MembershipPurchaseSettlementSubscriber::class)
        ->args([
            service(PurchaseSettlement::class),
            service(MembershipPurchaseStore::class),
            service('shopwell.membership.purchase_attach'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(MembershipPurchaseRoute::class)
        ->public()
        ->args([
            service(MembershipPurchaseService::class),
            service(ExtensionDispatcher::class),
        ]);

    // the read side of the membership page; it owns the "what can be bought" payload on its own so the
    // loyalty route does not have to know that memberships exist
    $services->set(MembershipRoute::class)
        ->public()
        ->args([
            service(MembershipOfferService::class),
            service(MembershipSubscriptionService::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(MembershipExpirationTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(MembershipExpirationTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(MembershipSubscriptionService::class),
        ])
        ->tag('messenger.message_handler');
};

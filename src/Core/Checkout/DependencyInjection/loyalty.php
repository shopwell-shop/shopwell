<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Cart\CartLocker;
use Shopwell\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartService;
use Shopwell\Core\Checkout\Ledger\RowLock;
use Shopwell\Core\Checkout\Loyalty\Api\LoyaltyController;
use Shopwell\Core\Checkout\Loyalty\Cart\LoyaltyPointCartProcessor;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyAccount\LoyaltyAccountDefinition;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointLot\LoyaltyPointLotDefinition;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointLotUse\LoyaltyPointLotUseDefinition;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointTransaction\LoyaltyPointTransactionDefinition;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyRedemptionLevel\LoyaltyRedemptionLevelDefinition;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\Aggregate\LoyaltyTierTranslation\LoyaltyTierTranslationDefinition;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierDefinition;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTierBenefit\LoyaltyTierBenefitDefinition;
use Shopwell\Core\Checkout\Loyalty\SalesChannel\LoyaltyRedeemRoute;
use Shopwell\Core\Checkout\Loyalty\SalesChannel\LoyaltyRoute;
use Shopwell\Core\Checkout\Loyalty\ScheduledTask\LoyaltyPointExpirationTask;
use Shopwell\Core\Checkout\Loyalty\ScheduledTask\LoyaltyPointExpirationTaskHandler;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyAccountService;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyBenefitResolver;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyLedger;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyPointLots;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyPointService;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyRedemptionCalculator;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyRefundSource;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyReturnPoints;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltySettings;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyTierProgress;
use Shopwell\Core\Checkout\Loyalty\Service\TierResolver;
use Shopwell\Core\Checkout\Loyalty\Subscriber\LoyaltyAccountProvisionSubscriber;
use Shopwell\Core\Checkout\Loyalty\Subscriber\OrderReturnStateChangeLoyaltySubscriber;
use Shopwell\Core\Checkout\Loyalty\Subscriber\OrderStateChangeLoyaltySubscriber;
use Shopwell\Core\Checkout\Loyalty\Subscriber\WalletTopUpLoyaltySubscriber;
use Shopwell\Core\Checkout\Membership\Service\MembershipSubscriptionService;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(LoyaltyAccountDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(LoyaltyPointTransactionDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(LoyaltyTierDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(LoyaltyTierTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(LoyaltyRedemptionLevelDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(LoyaltyTierBenefitDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(LoyaltyPointLotDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(LoyaltyPointLotUseDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(LoyaltyPointLots::class)
        ->args([
            service(Connection::class),
            service('loyalty_point_lot.repository'),
            service('loyalty_point_lot_use.repository'),
            service(ClockInterface::class),
        ]);

    $services->set(LoyaltyLedger::class)
        ->args([
            service('loyalty_account.repository'),
            service('loyalty_point_transaction.repository'),
            service(Connection::class),
            service(RowLock::class),
            service(LoyaltyPointLots::class),
        ]);

    $services->set(LoyaltySettings::class)
        ->args([
            service(SystemConfigService::class),
        ]);

    // pure projection over the loyalty payload - no dependencies, so both the rewards page and the
    // account overview print the same tier ladder
    $services->set(LoyaltyTierProgress::class);

    $services->set(LoyaltyAccountService::class)
        ->args([
            service('loyalty_account.repository'),
            service(TierResolver::class),
            service(RowLock::class),
        ]);

    $services->set(LoyaltyRedemptionCalculator::class)
        ->args([
            service('loyalty_redemption_level.repository'),
            service(LoyaltySettings::class),
        ]);

    $services->set(LoyaltyPointService::class)
        ->args([
            service(LoyaltyLedger::class),
            service(LoyaltyPointLots::class),
            service(LoyaltySettings::class),
            service('event_dispatcher'),
            service(ClockInterface::class),
            service('logger'),
        ]);

    // what a return does to the points of an order: gives back what it spent, takes back what it earned
    $services->set(LoyaltyReturnPoints::class)
        ->args([
            service('loyalty_point_transaction.repository'),
            service(LoyaltyPointLots::class),
            service(LoyaltyLedger::class),
        ]);

    // the points program is one of the sources a return is paid out of, and it settles in points;
    // the wallet carries priority 100 so the money the customer put in comes back before the discount
    $services->set(LoyaltyRefundSource::class)
        ->args([
            service(LoyaltyReturnPoints::class),
        ])
        ->tag('shopwell.order_return.refund_source');

    $services->set(OrderReturnStateChangeLoyaltySubscriber::class)
        ->args([
            service('order_return.repository'),
            service('order.repository'),
            service(LoyaltyReturnPoints::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(TierResolver::class)
        ->args([
            service('loyalty_tier.repository'),
            service('loyalty_account.repository'),
            service('customer.repository'),
            service(LoyaltySettings::class),
            service('event_dispatcher'),
            service(ClockInterface::class),
        ]);

    $services->set(LoyaltyBenefitResolver::class)
        ->args([
            service('loyalty_tier_benefit.repository'),
            service(TierResolver::class),
        ]);

    $services->set(LoyaltyAccountProvisionSubscriber::class)
        ->args([
            service(LoyaltyAccountService::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(OrderStateChangeLoyaltySubscriber::class)
        ->args([
            service('order.repository'),
            service(LoyaltyPointService::class),
            service(LoyaltyAccountService::class),
            service(TierResolver::class),
            service(LoyaltyBenefitResolver::class),
            service(LoyaltySettings::class),
        ])
        ->tag('kernel.event_subscriber');

    // the second way to earn points: money paid into the wallet. The wallet announces the credit,
    // this listener prices it - the wallet itself knows nothing about the points program
    $services->set(WalletTopUpLoyaltySubscriber::class)
        ->args([
            service(LoyaltyPointService::class),
            service(LoyaltySettings::class),
            service(LoyaltyBenefitResolver::class),
            service('logger'),
        ])
        ->tag('kernel.event_subscriber');

    // runs after the credit processor (priority 0) so the derived line items are re-clamped on every calculation
    $services->set(LoyaltyPointCartProcessor::class)
        ->args([
            service(AbsolutePriceCalculator::class),
            service(LoyaltyRedemptionCalculator::class),
            service(LoyaltySettings::class),
            service(LoyaltyAccountService::class),
        ])
        ->tag('shopwell.cart.processor', ['priority' => -1000]);

    $services->set(LoyaltyController::class)
        ->public()
        ->args([
            service('loyalty_account.repository'),
            service('loyalty_point_transaction.repository'),
            service('loyalty_tier.repository'),
            service(LoyaltyPointService::class),
            service(MembershipSubscriptionService::class),
            service(LoyaltyBenefitResolver::class),
        ]);

    // the rewards page reads points, tiers and the redemption ladder from here; the membership offer it
    // used to carry moved to MembershipRoute when the account page was split
    $services->set(LoyaltyRoute::class)
        ->public()
        ->args([
            service('loyalty_account.repository'),
            service('loyalty_tier.repository'),
            service(LoyaltySettings::class),
            service(LoyaltyBenefitResolver::class),
            service(LoyaltyRedemptionCalculator::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(LoyaltyRedeemRoute::class)
        ->public()
        ->args([
            service(LoyaltyAccountService::class),
            service(LoyaltySettings::class),
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
            service(LoyaltyPointService::class),
        ])
        ->tag('messenger.message_handler');
};

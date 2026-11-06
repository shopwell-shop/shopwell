<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Checkout\Cart\CartLocker;
use Shopwell\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartService;
use Shopwell\Core\Checkout\Ledger\RowLock;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseAttach;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseSettlement;
use Shopwell\Core\Checkout\Payment\Storefront\StorefrontPaymentContextResolver;
use Shopwell\Core\Checkout\Payment\Storefront\StorefrontPaymentService;
use Shopwell\Core\Checkout\Wallet\Api\WalletController;
use Shopwell\Core\Checkout\Wallet\Cart\WalletCartProcessor;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet\CustomerWalletDefinition;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTopUp\CustomerWalletTopUpDefinition;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction\CustomerWalletTransactionDefinition;
use Shopwell\Core\Checkout\Wallet\Purchase\WalletTopUpPurchaseStore;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletPaymentRoute;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletPresenter;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletRoute;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletTopUpCreateRoute;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletTopUpStatusRoute;
use Shopwell\Core\Checkout\Wallet\Service\WalletAccountService;
use Shopwell\Core\Checkout\Wallet\Service\WalletAdjustmentService;
use Shopwell\Core\Checkout\Wallet\Service\WalletLedger;
use Shopwell\Core\Checkout\Wallet\Service\WalletPaymentService;
use Shopwell\Core\Checkout\Wallet\Service\WalletRefundSource;
use Shopwell\Core\Checkout\Wallet\Service\WalletSettings;
use Shopwell\Core\Checkout\Wallet\Service\WalletTopUpService;
use Shopwell\Core\Checkout\Wallet\Subscriber\WalletAccountProvisionSubscriber;
use Shopwell\Core\Checkout\Wallet\Subscriber\WalletOrderSubscriber;
use Shopwell\Core\Checkout\Wallet\Subscriber\WalletTopUpSettlementSubscriber;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CustomerWalletDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(CustomerWalletTransactionDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(CustomerWalletTopUpDefinition::class)
        ->tag('shopwell.entity.definition');

    // the marker a top-up carries on its payment order. Built and read through one object, so the two
    // directions cannot drift apart
    $services->set('shopwell.wallet.purchase_attach', PurchaseAttach::class)
        ->args([
            'wallet_top_up:',
        ]);

    $services->set(WalletLedger::class)
        ->args([
            service('customer_wallet.repository'),
            service('customer_wallet_transaction.repository'),
            service(Connection::class),
            service(RowLock::class),
            service('event_dispatcher'),
        ]);

    $services->set(WalletSettings::class)
        ->args([
            service(SystemConfigService::class),
        ]);

    $services->set(WalletAccountService::class)
        ->args([
            service('customer_wallet.repository'),
        ]);

    $services->set(WalletPaymentService::class)
        ->args([
            service(WalletLedger::class),
        ]);

    // the wallet is one of the sources a return is paid out of, see OrderReturnRefundSource.
    // Priority: the money the customer put in goes back before the points discount is restored
    $services->set(WalletRefundSource::class)
        ->args([
            service('customer_wallet_transaction.repository'),
            service(WalletPaymentService::class),
        ])
        ->tag('shopwell.order_return.refund_source', ['priority' => 100]);

    $services->set(WalletAdjustmentService::class)
        ->args([
            service(WalletLedger::class),
            service(WalletSettings::class),
        ]);

    $services->set(WalletTopUpService::class)
        ->args([
            service('customer_wallet_top_up.repository'),
            service(StorefrontPaymentService::class),
            service(WalletSettings::class),
            service('shopwell.wallet.purchase_attach'),
        ]);

    $services->set(WalletTopUpPurchaseStore::class)
        ->args([
            service('customer_wallet_top_up.repository'),
            service(WalletLedger::class),
            service(RowLock::class),
        ]);

    $services->set(WalletTopUpSettlementSubscriber::class)
        ->args([
            service(PurchaseSettlement::class),
            service(WalletTopUpPurchaseStore::class),
            service('shopwell.wallet.purchase_attach'),
            service('customer_wallet_top_up.repository'),
            service('event_dispatcher'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(WalletOrderSubscriber::class)
        ->args([
            service('order.repository'),
            service(WalletAccountService::class),
            service(WalletPaymentService::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(WalletAccountProvisionSubscriber::class)
        ->args([
            service(WalletAccountService::class),
        ])
        ->tag('kernel.event_subscriber');

    // runs after the credit processor (priority 0) so the derived line items are re-clamped on every calculation
    $services->set(WalletCartProcessor::class)
        ->args([
            service(AbsolutePriceCalculator::class),
            service(WalletSettings::class),
            service(WalletAccountService::class),
        ])
        ->tag('shopwell.cart.processor', ['priority' => -1100]);

    $services->set(WalletPresenter::class);

    $services->set(WalletController::class)
        ->public()
        ->args([
            service(WalletAdjustmentService::class),
        ]);

    $services->set(WalletRoute::class)
        ->public()
        ->args([
            service('customer_wallet.repository'),
            service('customer_wallet_transaction.repository'),
            service(WalletTopUpService::class),
            service(WalletPresenter::class),
            service(ExtensionDispatcher::class),
            service(StorefrontPaymentContextResolver::class),
        ]);

    $services->set(WalletTopUpCreateRoute::class)
        ->public()
        ->args([
            service(WalletTopUpService::class),
            service(ExtensionDispatcher::class),
            service(StorefrontPaymentContextResolver::class),
        ]);

    $services->set(WalletTopUpStatusRoute::class)
        ->public()
        ->args([
            service(WalletTopUpService::class),
            service(WalletPresenter::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(WalletPaymentRoute::class)
        ->public()
        ->args([
            service(WalletAccountService::class),
            service(CartService::class),
            service(CartLocker::class),
            service(ExtensionDispatcher::class),
        ]);
};

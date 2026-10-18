<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Cart\CartLocker;
use Shopwell\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartService;
use Shopwell\Core\Checkout\Payment\Storefront\PlatformPaymentApp;
use Shopwell\Core\Checkout\Payment\Storefront\StorefrontPaymentService;
use Shopwell\Core\Checkout\Wallet\Api\WalletController;
use Shopwell\Core\Checkout\Wallet\Cart\WalletCartProcessor;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet\CustomerWalletDefinition;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTopUp\CustomerWalletTopUpDefinition;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction\CustomerWalletTransactionDefinition;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletPaymentRoute;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletRoute;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletTopUpCreateRoute;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletTopUpSimulateRoute;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletTopUpStatusRoute;
use Shopwell\Core\Checkout\Wallet\Service\WalletLedger;
use Shopwell\Core\Checkout\Wallet\Service\WalletService;
use Shopwell\Core\Checkout\Wallet\Service\WalletTopUpService;
use Shopwell\Core\Checkout\Wallet\Subscriber\WalletAccountProvisionSubscriber;
use Shopwell\Core\Checkout\Wallet\Subscriber\WalletOrderSubscriber;
use Shopwell\Core\Checkout\Wallet\Subscriber\WalletTopUpSettlementSubscriber;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\System\Payment\Order\PaymentOrderCapture;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
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

    // ---- the payments the shop collects for its own customers ----
    // A recharge is a payment order like the one of an external app, only the app is the platform
    // tenant that the payment migration seeds - the gateway itself is only called, never reworked.
    $services->set(StorefrontPaymentService::class)
        ->args([
            service(PlatformPaymentApp::class),
            service(PaymentOrderService::class),
            service(PaymentOrderCapture::class),
        ]);

    $services->set(WalletLedger::class)
        ->args([
            service('customer_wallet.repository'),
            service('customer_wallet_transaction.repository'),
            service(Connection::class),
            service('event_dispatcher'),
        ]);

    $services->set(WalletService::class)
        ->args([
            service('customer_wallet.repository'),
            service(WalletLedger::class),
            service(SystemConfigService::class),
        ]);

    $services->set(WalletTopUpService::class)
        ->args([
            service('customer_wallet_top_up.repository'),
            service(WalletService::class),
            service(StorefrontPaymentService::class),
            service(SystemConfigService::class),
            service(Connection::class),
            service(ClockInterface::class),
        ]);

    $services->set(WalletTopUpSettlementSubscriber::class)
        ->args([
            service(WalletTopUpService::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(WalletOrderSubscriber::class)
        ->args([
            service('order.repository'),
            service(WalletService::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(WalletAccountProvisionSubscriber::class)
        ->args([
            service(WalletService::class),
        ])
        ->tag('kernel.event_subscriber');

    // runs after the credit processor (priority 0) so the derived line items are re-clamped on every calculation
    $services->set(WalletCartProcessor::class)
        ->args([
            service(AbsolutePriceCalculator::class),
            service(SystemConfigService::class),
            service(WalletService::class),
        ])
        ->tag('shopwell.cart.processor', ['priority' => -1100]);

    $services->set(WalletController::class)
        ->public()
        ->args([
            service(WalletService::class),
        ]);

    $services->set(WalletRoute::class)
        ->public()
        ->args([
            service('customer_wallet.repository'),
            service('customer_wallet_transaction.repository'),
            service(WalletTopUpService::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(WalletTopUpCreateRoute::class)
        ->public()
        ->args([
            service(WalletTopUpService::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(WalletTopUpStatusRoute::class)
        ->public()
        ->args([
            service(WalletTopUpService::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(WalletTopUpSimulateRoute::class)
        ->public()
        ->args([
            service(WalletTopUpService::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(WalletPaymentRoute::class)
        ->public()
        ->args([
            service(WalletService::class),
            service(CartService::class),
            service(CartLocker::class),
            service(ExtensionDispatcher::class),
        ]);
};

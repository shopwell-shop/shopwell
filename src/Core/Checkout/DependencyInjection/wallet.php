<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DependencyInjection;

use Shopwell\Core\Checkout\Cart\CartLocker;
use Shopwell\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartService;
use Shopwell\Core\Checkout\Wallet\Api\WalletController;
use Shopwell\Core\Checkout\Wallet\Cart\WalletCartProcessor;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet\CustomerWalletDefinition;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction\CustomerWalletTransactionDefinition;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletPaymentRoute;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletRoute;
use Shopwell\Core\Checkout\Wallet\Service\WalletService;
use Shopwell\Core\Checkout\Wallet\Subscriber\WalletAccountProvisionSubscriber;
use Shopwell\Core\Checkout\Wallet\Subscriber\WalletOrderSubscriber;
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

    $services->set(WalletService::class)
        ->args([
            service('customer_wallet.repository'),
            service('customer_wallet_transaction.repository'),
            service(SystemConfigService::class),
            service('event_dispatcher'),
        ]);

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

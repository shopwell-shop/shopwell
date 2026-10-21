<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DependencyInjection;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\OrderReturn\Api\OrderReturnController;
use Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnLineItem\OrderReturnLineItemEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnMessage\OrderReturnMessageEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnReason\OrderReturnReasonEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\SalesChannel\OrderReturnGuestRoute;
use Shopwell\Core\Checkout\OrderReturn\SalesChannel\OrderReturnPresenter;
use Shopwell\Core\Checkout\OrderReturn\SalesChannel\OrderReturnRoute;
use Shopwell\Core\Checkout\OrderReturn\ScheduledTask\OrderReturnDeadlineTask;
use Shopwell\Core\Checkout\OrderReturn\ScheduledTask\OrderReturnDeadlineTaskHandler;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnNumberGenerator;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnGuestTokenService;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnQuantityGuard;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnRefundService;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnService;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnSettings;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnStateHandler;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnStockRestorer;
use Shopwell\Core\Checkout\OrderReturn\Subscriber\OrderReturnRefundFinishedSubscriber;
use Shopwell\Core\Content\Product\Stock\StockStorage;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundService;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // ---- the return, the counter part of the order ----
    $services->set(OrderReturnEntity::class)
        ->tag('shopwell.entity');

    $services->set(OrderReturnLineItemEntity::class)
        ->tag('shopwell.entity');

    $services->set(OrderReturnMessageEntity::class)
        ->tag('shopwell.entity');

    $services->set(OrderReturnReasonEntity::class)
        ->tag('shopwell.entity');

    // ---- the rules of the shop ----
    $services->set(OrderReturnSettings::class)
        ->args([
            service(SystemConfigService::class),
        ]);

    $services->set(OrderReturnNumberGenerator::class)
        ->args([
            service(ClockInterface::class),
        ]);

    $services->set(OrderReturnStateHandler::class)
        ->args([
            service(StateMachineRegistry::class),
        ]);

    $services->set(OrderReturnQuantityGuard::class)
        ->args([
            service('order_return.repository'),
            service('order_return_line_item.repository'),
            service('state_machine.repository'),
            service('state_machine_state.repository'),
        ]);

    $services->set(OrderReturnStockRestorer::class)
        ->args([
            service('order_return_line_item.repository'),
            service(StockStorage::class),
        ]);

    // ---- the money: the gateway refunds, the return only hands it over ----
    $services->set(OrderReturnRefundService::class)
        ->args([
            service('payment_app.repository'),
            service('order_return.repository'),
            service(PaymentOrderService::class),
            service(PaymentRefundService::class),
            service(OrderReturnStateHandler::class),
        ]);

    $services->set(OrderReturnService::class)
        ->args([
            service('order_return.repository'),
            service('order_return_line_item.repository'),
            service('order_return_message.repository'),
            service('order_return_reason.repository'),
            service('order.repository'),
            service(OrderReturnSettings::class),
            service(OrderReturnNumberGenerator::class),
            service(OrderReturnStateHandler::class),
            service(OrderReturnQuantityGuard::class),
            service(OrderReturnStockRestorer::class),
            service(OrderReturnRefundService::class),
            service(InitialStateIdLoader::class),
            service('event_dispatcher'),
            service(ClockInterface::class),
        ]);

    // ---- the confirmation of the gateway closes the return ----
    $services->set(OrderReturnRefundFinishedSubscriber::class)
        ->args([
            service('order_return.repository'),
            service(OrderReturnStateHandler::class),
            service('event_dispatcher'),
        ])
        ->tag('kernel.event_subscriber');

    // ---- the deadlines of the returns ----
    $services->set(OrderReturnDeadlineTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(OrderReturnDeadlineTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service('order_return.repository'),
            service(OrderReturnService::class),
            service(OrderReturnSettings::class),
            service(ClockInterface::class),
        ])
        ->tag('messenger.message_handler');

    // ---- the api of the return ----
    $services->set(OrderReturnPresenter::class);

    $services->set(OrderReturnGuestTokenService::class)
        ->args([
            param('kernel.secret'),
        ]);

    $services->set(OrderReturnRoute::class)
        ->public()
        ->args([
            service(OrderReturnService::class),
            service('order_return.repository'),
            service('order.repository'),
            service(OrderReturnPresenter::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(OrderReturnGuestRoute::class)
        ->public()
        ->args([
            service(OrderReturnService::class),
            service(OrderReturnGuestTokenService::class),
            service(OrderReturnSettings::class),
            service('order_return.repository'),
            service('order.repository'),
            service(OrderReturnPresenter::class),
        ]);

    $services->set(OrderReturnController::class)
        ->public()
        ->args([
            service(OrderReturnService::class),
            service(OrderReturnPresenter::class),
        ]);
};

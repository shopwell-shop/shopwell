<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Shopwell\Core\System\Payment\Account\Api\PaymentAccountController;
use Shopwell\Core\System\Payment\Account\PaymentAccountEntity;
use Shopwell\Core\System\Payment\Account\PaymentAccountIdentitySubscriber;
use Shopwell\Core\System\Payment\Account\PaymentAccountLock;
use Shopwell\Core\System\Payment\Account\PaymentAccountService;
use Shopwell\Core\System\Payment\Api\NotifyController;
use Shopwell\Core\System\Payment\Api\PaymentApiRouteScope;
use Shopwell\Core\System\Payment\Api\PaymentCashierController;
use Shopwell\Core\System\Payment\Api\PaymentChannelController;
use Shopwell\Core\System\Payment\Api\PaymentContextParser;
use Shopwell\Core\System\Payment\Api\PaymentOrderController;
use Shopwell\Core\System\Payment\Api\PaymentRefundController;
use Shopwell\Core\System\Payment\Api\PaymentTransferNotifyController;
use Shopwell\Core\System\Payment\Api\ReturnController;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppAccount\PaymentAppAccountEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Api\PaymentAppController;
use Shopwell\Core\System\Payment\App\PaymentAppAuthenticator;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentAppProvisioner;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\App\PaymentSignatureService;
use Shopwell\Core\System\Payment\Cashier\PaymentCashierConfigEntity;
use Shopwell\Core\System\Payment\Cashier\PaymentCashierService;
use Shopwell\Core\System\Payment\Cashier\PaymentCashierSessionEntity;
use Shopwell\Core\System\Payment\Cashier\PaymentCashierSessionLock;
use Shopwell\Core\System\Payment\Command\PaymentProvidersPruneCommand;
use Shopwell\Core\System\Payment\Command\PaymentProvidersValidateCommand;
use Shopwell\Core\System\Payment\Gateway\PaymentChannelCloser;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayCaptureAction;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayCloseAction;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayNotifyAction;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayQueryAction;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayRefundAction;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayTransferAction;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayTransferQueryAction;
use Shopwell\Core\System\Payment\Integration\Alipay\AlipayProvider;
use Shopwell\Core\System\Payment\Integration\Alipay\Notification\AlipayNotificationExtractor;
use Shopwell\Core\System\Payment\Integration\Alipay\Notification\AlipayTransferNotificationVerifier;
use Shopwell\Core\System\Payment\Integration\Alipay\Request\AlipayPayParamsDecoder;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatCaptureAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatCloseAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatNotifyAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatQueryAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatRefundAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatTransferAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatTransferQueryAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Notification\WechatNotificationExtractor;
use Shopwell\Core\System\Payment\Integration\Wechat\Notification\WechatTransferNotificationVerifier;
use Shopwell\Core\System\Payment\Integration\Wechat\Request\WechatPayParamsDecoder;
use Shopwell\Core\System\Payment\Integration\Wechat\WechatProvider;
use Shopwell\Core\System\Payment\Method\PaymentMethod;
use Shopwell\Core\System\Payment\Method\PaymentMethodRegistry;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationEntity;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationProcessor;
use Shopwell\Core\System\Payment\Notification\PaymentNotifyUrlBuilder;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptEntity;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundEntity;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionEntity;
use Shopwell\Core\System\Payment\Order\Event\PaymentEventDispatcher;
use Shopwell\Core\System\Payment\Order\PaymentOrderCloser;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderPaidHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderPresenter;
use Shopwell\Core\System\Payment\Order\PaymentOrderQueryResultHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderReconciler;
use Shopwell\Core\System\Payment\Order\PaymentOrderRowLock;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStarter;
use Shopwell\Core\System\Payment\Order\PaymentOrderStateHandler;
use Shopwell\Core\System\Payment\Order\PaymentRefundReconciler;
use Shopwell\Core\System\Payment\Order\PaymentRefundService;
use Shopwell\Core\System\Payment\Order\PaymentRefundStateHandler;
use Shopwell\Core\System\Payment\Product\PaymentProductRegistry;
use Shopwell\Core\System\Payment\Provider\Config\Api\PaymentProviderConfigController;
use Shopwell\Core\System\Payment\Provider\Config\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigReader;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\Config\PaymentProviderConfigEntity;
use Shopwell\Core\System\Payment\Provider\Config\PaymentProviderConfigService;
use Shopwell\Core\System\Payment\Provider\PaymentProviderFactory;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Routing\DefaultPaymentRoutingPolicy;
use Shopwell\Core\System\Payment\Routing\PaymentProductEligibility;
use Shopwell\Core\System\Payment\Routing\PaymentRouteResolver;
use Shopwell\Core\System\Payment\ScheduledTask\DispatchPaymentEventsTask;
use Shopwell\Core\System\Payment\ScheduledTask\DispatchPaymentEventsTaskHandler;
use Shopwell\Core\System\Payment\ScheduledTask\ExpirePaymentOrdersTask;
use Shopwell\Core\System\Payment\ScheduledTask\ExpirePaymentOrdersTaskHandler;
use Shopwell\Core\System\Payment\ScheduledTask\ReconcileOpenPaymentOrdersTask;
use Shopwell\Core\System\Payment\ScheduledTask\ReconcileOpenPaymentOrdersTaskHandler;
use Shopwell\Core\System\Payment\ScheduledTask\ReconcileOpenPaymentRefundsTask;
use Shopwell\Core\System\Payment\ScheduledTask\ReconcileOpenPaymentRefundsTaskHandler;
use Shopwell\Core\System\Payment\ScheduledTask\ReconcileOpenPaymentTransfersTask;
use Shopwell\Core\System\Payment\ScheduledTask\ReconcileOpenPaymentTransfersTaskHandler;
use Shopwell\Core\System\Payment\Subscription\PaymentMandateEntity;
use Shopwell\Core\System\Payment\Subscription\PaymentPlanEntity;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionCycleEntity;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionEntity;
use Shopwell\Core\System\Payment\Transfer\Api\PaymentTransferController;
use Shopwell\Core\System\Payment\Transfer\Api\PaymentTransferPresenter;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationProcessor;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationVerifierRegistry;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferBooking;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferReconciler;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferResultBooker;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStateHandler;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferSubmitter;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDeliveryEntity;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDispatcher;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookSigner;
use Shopwell\Core\System\Payment\Webhook\ScheduledTask\DeliverPaymentWebhookTask;
use Shopwell\Core\System\Payment\Webhook\ScheduledTask\DeliverPaymentWebhookTaskHandler;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service_locator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * The generic payment gateway: the api the external apps call, the payment interfaces and their
 * channels, and the bookkeeping of everything that happened.
 *
 * A payment interface is a declaration plus the actions it consists of: `AbstractPaymentProvider`
 * names its credentials, its channels, its capabilities and its transfer and cycle rules, and its
 * actions are the code that talks to it. Adding another interface of the platform is one declaration
 * service next to the api of that interface - an interface of an extension is one declaration service
 * tagged `shopwell.payment.provider` in the extension, and this file does not change for it.
 *
 * @codeCoverageIgnore
 */
return static function (ContainerConfigurator $container): void {
    // the interfaces are not told where to notify the platform by an app but by this url, so an
    // installation behind a proxy can pin it instead of trusting the host header of a request
    $container->parameters()->set('shopwell.payment.notify_base_url', '');

    // which declaration decorates which, per technical name, outermost first. Filled by
    // `PaymentProviderCompilerPass` and only read by `payment:providers:validate`.
    $container->parameters()->set('shopwell.payment.provider.decoration_chain', []);

    $services = $container->services();

    $services->set(PaymentCashierConfigEntity::class)->tag('shopwell.entity');
    $services->set(PaymentCashierSessionEntity::class)->tag('shopwell.entity');

    // ---- App: the apps that call the payment api, and their secrets ----
    $services->set(PaymentAccountEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentAppEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentAppAccountEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentAppChannelEntity::class)
        ->tag('shopwell.entity');

    // ---- the secrets ----
    $services->set(PaymentCredentialCipher::class)
        ->args([env('APP_SECRET')]);

    $services->set(PaymentSignatureService::class);

    $services->set(PaymentAppAuthenticator::class)
        ->args([
            service('payment_app.repository'),
            service(PaymentSignatureService::class),
            service(PaymentCredentialCipher::class),
            service('cache.object'),
            service('lock.factory'),
        ]);

    // onboards the apps that call the payment api and hands them their secret, so it is an admin
    // endpoint as well - the secret never travels through the generic entity endpoints; the writing
    // itself lives in the provisioner, the controller only translates request and response
    $services->set(PaymentAppProvisioner::class)
        ->args([
            service('payment_app.repository'),
            service('payment_app_account.repository'),
            service('payment_app_channel.repository'),
            service(PaymentCredentialCipher::class),
            service(PaymentProviderRegistry::class),
            service(PaymentProductRegistry::class),
            service(ClockInterface::class),
            service(Connection::class),
            service('payment_account.repository'),
        ]);

    $services->set(PaymentAppController::class)
        ->public()
        ->args([
            service('payment_app.repository'),
            service(PaymentAppProvisioner::class),
            // non-secret merchant fields are read back; secret fields only report their configured state
            service(PaymentAccountService::class),
        ])
        ->call('setContainer', [service('service_container')])
        ->tag('controller.service_arguments');

    // ---- Provider: the payment interfaces, their channels and the merchant configuration ----
    $services->set(PaymentProviderConfigEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentChannelConfigEntity::class)
        ->tag('shopwell.entity');

    // ---- the payment interfaces ----
    // One notification extractor per interface; the actions of the interfaces and their declarations
    // live in the Gateway section below, together with the api they talk to.
    $services->set(AlipayNotificationExtractor::class);
    $services->set(WechatNotificationExtractor::class);

    // An interface that can hand a returning payer back to the answer it already gave registers the
    // decoder here; an interface without one is simply captured again on a reopen.
    $services->set(AlipayPayParamsDecoder::class);
    $services->set(WechatPayParamsDecoder::class);

    $services->set(PaymentConfigReader::class);

    foreach (['alipay', 'wechat'] as $method) {
        $services->set('shopwell.payment.method.' . $method, PaymentMethod::class)
            ->args([$method, 'sw-settings-payment-provider.provider.' . $method])
            ->tag('shopwell.payment.method_definition');
    }
    $services->set(PaymentMethodRegistry::class)
        ->args([tagged_iterator('shopwell.payment.method_definition')]);
    $services->set(PaymentProviderFactory::class)
        ->args([service(PaymentConfigReader::class), service(PaymentMethodRegistry::class), service('event_dispatcher')]);

    // The declarations are collected by `PaymentProviderCompilerPass`, which fills this argument with
    // one definition per technical name. An installation that ships no interface at all keeps the empty
    // array, so the registry is never a reason for the container to fail.
    //
    // It is public because `payment:providers:validate` resolves it through a locator inside its
    // `execute()`: the registry builds every declaration when it is constructed, so a broken one has to
    // be reachable from the command in order to be reported instead of crashing the container.
    $services->set(PaymentProviderRegistry::class)
        ->args([[]])
        ->public();

    // the channels are indexed across the interfaces, and a name may only be used once - an app names
    // the way it wants to be paid and the platform derives the interface (and its credentials) from it
    $services->set(PaymentProductRegistry::class)
        ->args([service(PaymentProviderRegistry::class)]);

    // the CI and the operator entry point: it prints every interface with its channels, its
    // capabilities and the decoration chain, and fails when a declaration is not coherent. It is
    // handed a locator holding the provider registry, not the registry itself: the registry builds
    // every declaration while it is constructed, so injecting it would throw while the container
    // wires the command - before execute() runs and before anything can report the broken
    // declaration. The channel index is built inside the command for the same reason: it is the
    // thing that refuses a duplicated channel name.
    $services->set(PaymentProvidersValidateCommand::class)
        ->args([
            service_locator([
                PaymentProviderRegistry::class => service(PaymentProviderRegistry::class),
            ]),
            param('shopwell.payment.provider.decoration_chain'),
        ])
        ->tag('console.command');

    // the operator counterpart of the validation: it reports - and only with `--force` deletes - the
    // configuration rows of interfaces and channels this installation does not declare anymore. The
    // registry travels in a locator for the very same reason as above: its construction is what
    // validates the declarations, so it has to happen inside execute() where a broken declaration can
    // be reported instead of breaking the container
    $services->set(PaymentProvidersPruneCommand::class)
        ->args([
            service_locator([
                PaymentProviderRegistry::class => service(PaymentProviderRegistry::class),
            ]),
            service('payment_provider_config.repository'),
            service('payment_channel_config.repository'),
            service('payment_app_account.repository'),
            service('payment_app_channel.repository'),
        ])
        ->tag('console.command');

    $services->set(PaymentAccountService::class)
        ->args([
            service('payment_account.repository'),
            service('payment_app.repository'),
            service('payment_provider_config.repository'),
            service(PaymentProviderRegistry::class),
            service(PaymentCredentialCipher::class),
            service(Connection::class),
            service(PaymentAccountLock::class),
        ]);
    $services->set(PaymentAccountController::class)
        ->public()
        ->args([service(PaymentAccountService::class)])
        ->call('setContainer', [service('service_container')])
        ->tag('controller.service_arguments');

    $services->set(PaymentAccountLock::class)->args([service(Connection::class)]);
    $services->set(PaymentAccountIdentitySubscriber::class)
        ->args([
            service(PaymentAccountLock::class),
            service(PaymentProviderRegistry::class),
            service(PaymentCredentialCipher::class),
        ])
        ->tag('kernel.event_subscriber');

    // ---- the resolution of one payment ----
    $services->set(PaymentConfigResolver::class)
        ->args([
            service(PaymentProviderRegistry::class),
            service(PaymentProductRegistry::class),
            service('payment_app_account.repository'),
            service('payment_app_channel.repository'),
            service(PaymentCredentialCipher::class),
            service('payment_account.repository'),
        ]);

    // the administration asks this one for the interfaces and their channels, so it sits behind the
    // admin session and the acl instead of the app authentication; the sealing and writing of the
    // merchant configuration lives in the config service
    $services->set(PaymentProviderConfigService::class)
        ->args([
            service('payment_provider_config.repository'),
            service('payment_channel_config.repository'),
            service('payment_account.repository'),
            service('payment_app.repository'),
            service('payment_app_account.repository'),
            service('payment_app_channel.repository'),
            service(Connection::class),
            service(PaymentAccountService::class),
        ]);

    $services->set(PaymentProviderConfigController::class)
        ->public()
        ->args([
            service(PaymentProviderRegistry::class),
            service(PaymentProviderConfigService::class),
            service(PaymentCredentialCipher::class),
            service(PaymentMethodRegistry::class),
        ])
        ->call('setContainer', [service('service_container')])
        ->tag('controller.service_arguments');

    // ---- Gateway: the payum integration ----
    // One gateway per interface, not per channel: the channels of an interface share the credentials,
    // the api and everything but the call that opens a payment, so the capture action dispatches on the
    // channel itself. The gateway is assembled per payment out of the declaration of its interface - the
    // actions are stateless services, the api is built per payment because it carries the resolved
    // credentials of the very payment.
    //
    // The priority of a declaration is fixed at -100 for the interfaces the platform ships: it is the
    // lowest one, so a plugin that extends one of them decorates it with a higher priority and the base
    // implementation stays the one the chain ends at.
    $services->set(AlipayCaptureAction::class)
        ->args([service(ClockInterface::class)]);
    $services->set(AlipayRefundAction::class);
    $services->set(AlipayNotifyAction::class);
    $services->set(AlipayQueryAction::class);
    $services->set(AlipayCloseAction::class);
    $services->set(AlipayTransferAction::class)
        ->args([service(PaymentCredentialCipher::class)]);
    $services->set(AlipayTransferQueryAction::class);

    $services->set(AlipayProvider::class)
        ->args([
            service(AlipayCaptureAction::class),
            service(AlipayRefundAction::class),
            service(AlipayNotifyAction::class),
            service(AlipayQueryAction::class),
            service(AlipayCloseAction::class),
            service(AlipayTransferAction::class),
            service(AlipayTransferQueryAction::class),
            service(AlipayNotificationExtractor::class),
            service(AlipayPayParamsDecoder::class),
            service(AlipayTransferNotificationVerifier::class),
        ])
        ->tag('shopwell.payment.provider', ['key' => 'alipay', 'priority' => -100]);

    $services->set(WechatCaptureAction::class);
    $services->set(WechatRefundAction::class);
    $services->set(WechatNotifyAction::class);
    $services->set(WechatQueryAction::class);
    $services->set(WechatCloseAction::class);
    $services->set(WechatTransferAction::class)
        ->args([service(PaymentCredentialCipher::class)]);
    $services->set(WechatTransferQueryAction::class);

    $services->set(WechatProvider::class)
        ->args([
            service(WechatCaptureAction::class),
            service(WechatRefundAction::class),
            service(WechatNotifyAction::class),
            service(WechatQueryAction::class),
            service(WechatCloseAction::class),
            service(WechatTransferAction::class),
            service(WechatTransferQueryAction::class),
            service(WechatNotificationExtractor::class),
            service(WechatPayParamsDecoder::class),
            service(WechatTransferNotificationVerifier::class),
        ])
        ->tag('shopwell.payment.provider', ['key' => 'wechat', 'priority' => -100]);

    $services->set(PaymentProductEligibility::class);
    $services->set(DefaultPaymentRoutingPolicy::class)
        ->tag('shopwell.payment.routing_policy');
    $services->set(PaymentRouteResolver::class)
        ->args([
            service(PaymentProductRegistry::class),
            service(PaymentProductEligibility::class),
            service(PaymentConfigResolver::class),
            tagged_iterator('shopwell.payment.routing_policy'),
        ]);

    // ---- Order: the payment order, its capture and its refunds ----
    $services->set(PaymentOrderEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentAttemptEntity::class)->tag('shopwell.entity');

    $services->set(PaymentTransactionEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentRefundEntity::class)
        ->tag('shopwell.entity');

    // the state machines of the payment order and its refunds are only driven through the handlers,
    // so the action names are named exactly once
    $services->set(PaymentOrderStateHandler::class)
        ->args([service(StateMachineRegistry::class)]);

    $services->set(PaymentOrderRowLock::class)
        ->args([service(Connection::class)]);

    $services->set(PaymentOrderService::class)
        ->args([
            service('payment_order.repository'),
            service(InitialStateIdLoader::class),
            service(PaymentOrderStateHandler::class),
            service(ClockInterface::class),
            service(AbstractNumberRangeValueGenerator::class),
            service(PaymentOrderRowLock::class),
            service('payment_attempt.repository'),
        ]);
    $services->set(PaymentOrderStarter::class)
        ->args([
            service('payment_transaction.repository'),
            service('payment_attempt.repository'),
            service('payment_order.repository'),
            service(PaymentOrderService::class),
            service(PaymentNotifyUrlBuilder::class),
            service(AbstractNumberRangeValueGenerator::class),
            service(PaymentOrderRowLock::class),
            service(PaymentAccountLock::class),
        ]);

    // the gateway side of closing: it talks to the channel and keeps the attempt as a transaction,
    // but it never touches the order - the state machine stays with PaymentOrderService
    $services->set(PaymentChannelCloser::class)
        ->args([
            service('payment_transaction.repository'),
            service(PaymentConfigResolver::class),
            service(AbstractNumberRangeValueGenerator::class),
        ]);

    // the orchestration of a close: the channel first, the order afterwards. It sits between the two
    // services that must not know each other, because the reconciliation needs PaymentOrderService
    $services->set(PaymentOrderCloser::class)
        ->args([
            service(PaymentChannelCloser::class),
            service(PaymentOrderService::class),
            service(PaymentOrderReconciler::class),
        ]);

    // the one path a payment order becomes paid: a notification and the reconciliation both write
    // the payment through here, so the two cannot drift apart
    $services->set(PaymentEventDispatcher::class)
        ->args([
            service('payment_order.repository'),
            service('payment_refund.repository'),
            service(PaymentWebhookDispatcher::class),
            service('event_dispatcher'),
            service(ClockInterface::class),
            service('logger'),
        ]);

    $services->set(PaymentOrderPaidHandler::class)
        ->args([
            service(PaymentOrderService::class),
            service(PaymentEventDispatcher::class),
        ]);

    // the write side of the reconciliation: the answer of a query becomes the same bookkeeping a
    // notification of the interface would have produced
    $services->set(PaymentOrderQueryResultHandler::class)
        ->args([
            service(PaymentOrderService::class),
            service(PaymentWebhookDispatcher::class),
            service(PaymentOrderPaidHandler::class),
            service(ClockInterface::class),
        ]);

    $services->set(PaymentRefundStateHandler::class)
        ->args([service(StateMachineRegistry::class)]);

    $services->set(PaymentRefundService::class)
        ->args([
            service('payment_refund.repository'),
            service(PaymentConfigResolver::class),
            service(PaymentOrderService::class),
            service(PaymentEventDispatcher::class),
            service(PaymentNotifyUrlBuilder::class),
            service(PaymentRefundStateHandler::class),
            service(InitialStateIdLoader::class),
            service(ClockInterface::class),
            service(AbstractNumberRangeValueGenerator::class),
            service(PaymentOrderRowLock::class),
        ]);

    $services->set(PaymentRefundReconciler::class)
        ->args([
            service('payment_refund.repository'),
            service('state_machine_state.repository'),
            service(PaymentRefundService::class),
            service(ClockInterface::class),
            service('logger'),
        ]);

    $services->set(ReconcileOpenPaymentRefundsTask::class)
        ->tag('shopwell.scheduled.task');
    $services->set(ReconcileOpenPaymentRefundsTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(PaymentRefundReconciler::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(DispatchPaymentEventsTask::class)
        ->tag('shopwell.scheduled.task');
    $services->set(DispatchPaymentEventsTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(PaymentEventDispatcher::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(PaymentOrderPresenter::class)
        ->args([
            service('state_machine_state.repository'),
        ]);

    $services->set(ExpirePaymentOrdersTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(ExpirePaymentOrdersTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(PaymentOrderService::class),
        ])
        ->tag('messenger.message_handler');

    // when a notification of an interface is lost or late, the platform asks the interface itself:
    // the reconciler queries the interface for every payment order that is open longer than a while
    // and brings the order in line with what the interface reports
    $services->set(PaymentOrderReconciler::class)
        ->args([
            service('payment_order.repository'),
            service('state_machine_state.repository'),
            service(PaymentConfigResolver::class),
            service(PaymentOrderService::class),
            service(PaymentOrderPaidHandler::class),
            service(PaymentOrderQueryResultHandler::class),
            service(ClockInterface::class),
            service('logger'),
        ]);

    $services->set(ReconcileOpenPaymentOrdersTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(ReconcileOpenPaymentOrdersTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(PaymentOrderReconciler::class),
        ])
        ->tag('messenger.message_handler');

    // ---- Notification: the asynchronous notifications of the interfaces ----
    $services->set(PaymentNotificationEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentNotifyUrlBuilder::class)
        ->args([
            service('request_stack'),
            param('shopwell.payment.notify_base_url'),
        ]);

    $services->set(PaymentNotificationProcessor::class)
        ->args([
            service('payment_notification.repository'),
            service(PaymentProviderRegistry::class),
            service(PaymentOrderService::class),
            service(PaymentRefundService::class),
            service(PaymentConfigResolver::class),
            service(PaymentOrderPaidHandler::class),
            service('payment_attempt.repository'),
        ]);

    // ---- Webhook: the signed deliveries of what happened to the apps ----
    $services->set(PaymentWebhookDeliveryEntity::class)
        ->tag('shopwell.entity');

    // ---- Subscription: the plans, their instances, the signing agreements and their cycles ----
    $services->set(PaymentPlanEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentMandateEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentSubscriptionEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentSubscriptionCycleEntity::class)
        ->tag('shopwell.entity');

    // ---- Transfer: the payouts of the platform to its recipients ----
    $services->set(PaymentTransferRecipientEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentTransferEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentTransferStateHandler::class)
        ->args([
            service(StateMachineRegistry::class),
        ]);

    $services->set(PaymentTransferRecipientService::class)
        ->args([
            service('payment_transfer_recipient.repository'),
            service(PaymentCredentialCipher::class),
            service(ClockInterface::class),
            service(PaymentProviderRegistry::class),
        ]);

    $services->set(PaymentTransferService::class)
        ->args([
            service('payment_transfer.repository'),
            service('payment_transfer_recipient.repository'),
            service(PaymentTransferStateHandler::class),
            service(PaymentTransferRecipientService::class),
            service(PaymentCredentialCipher::class),
            service(InitialStateIdLoader::class),
            service(ClockInterface::class),
            service(PaymentProviderRegistry::class),
            service(AbstractNumberRangeValueGenerator::class),
            service(PaymentConfigResolver::class),
            service(PaymentAccountLock::class),
        ]);

    // the two sides of a transfer that talk to the channel: submitting it, and asking what became of
    // it. They share the service as the only writer of the record and the resolver as the only way to
    // the credentials of the app
    $services->set(PaymentTransferResultBooker::class)
        ->args([
            service(PaymentTransferService::class),
            service(Connection::class),
        ]);

    $services->set(PaymentTransferSubmitter::class)
        ->args([
            service(PaymentConfigResolver::class),
            service(PaymentTransferService::class),
            service(PaymentTransferResultBooker::class),
        ]);

    $services->set(PaymentTransferReconciler::class)
        ->args([
            service(PaymentConfigResolver::class),
            service(PaymentTransferService::class),
            service('payment_transfer.repository'),
            service('state_machine_state.repository'),
            service(ClockInterface::class),
            service('logger'),
        ]);

    // the administration side of a payout: booking the intent (no channel involved), submitting it
    // (the one call that moves money out) and abandoning it. The listing of the receivers of a payout
    // is answered here as well, because the generic entity route would hand out the encrypted identity
    $services->set(PaymentTransferBooking::class)
        ->args([
            service('payment_app.repository'),
            service(PaymentProviderRegistry::class),
            service(PaymentTransferRecipientService::class),
            service(PaymentTransferService::class),
            service(ClockInterface::class),
        ]);

    // the payout rules of the interfaces are read from the snapshots in the registry - the matrix of
    // identity types, required recipient fields and currencies must exist exactly once, next to the
    // interface, and the administration must not interpret the raw declarations a second time
    $services->set(PaymentTransferPresenter::class)
        ->args([
            service(PaymentCredentialCipher::class),
        ]);

    $services->set(PaymentTransferController::class)
        ->public()
        ->args([
            service('payment_app.repository'),
            service('payment_transfer_recipient.repository'),
            service(PaymentTransferBooking::class),
            service(PaymentTransferSubmitter::class),
            service(PaymentTransferService::class),
            service(PaymentTransferPresenter::class),
            service(PaymentProviderRegistry::class),
            service(PaymentTransferResultBooker::class),
        ])
        ->tag('controller.service_arguments');

    // the payout side of the reconciliation: a submission may answer nothing at all, and only a
    // repeating question finds out what became of the money
    $services->set(ReconcileOpenPaymentTransfersTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(ReconcileOpenPaymentTransfersTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(PaymentTransferReconciler::class),
        ])
        ->tag('messenger.message_handler');

    // ---- Transfer notification: a verified trigger, never a source of state ----
    // The body only proves that the interface sent it and names the payout; what became of the payout
    // is asked for with the query of that interface, so the state mapping exists once
    $services->set(AlipayTransferNotificationVerifier::class)
        ->tag('shopwell.payment.transfer_notification_verifier');

    $services->set(WechatTransferNotificationVerifier::class)
        ->args([service('logger')])
        ->tag('shopwell.payment.transfer_notification_verifier');

    $services->set(PaymentTransferNotificationVerifierRegistry::class)
        ->args([tagged_iterator('shopwell.payment.transfer_notification_verifier')]);

    $services->set(PaymentTransferNotificationProcessor::class)
        ->args([
            service('payment_notification.repository'),
            service('payment_app.repository'),
            service(PaymentTransferNotificationVerifierRegistry::class),
            service(PaymentConfigResolver::class),
            service(PaymentTransferService::class),
            service(PaymentTransferReconciler::class),
        ]);

    // the interface calls this one, so it is not behind the app authentication
    $services->set(PaymentTransferNotifyController::class)
        ->public()
        ->args([
            service(PaymentTransferNotificationVerifierRegistry::class),
            service(PaymentTransferNotificationProcessor::class),
            service('logger'),
        ])
        ->tag('controller.service_arguments');

    // the payload and its signature are the contract with the app, so they are built in one place -
    // the dispatcher only queues, sends and retries
    $services->set(PaymentWebhookSigner::class)
        ->args([
            service(PaymentSignatureService::class),
            service(PaymentCredentialCipher::class),
            service(ClockInterface::class),
        ]);

    $services->set(PaymentWebhookDispatcher::class)
        ->args([
            service('payment_webhook_delivery.repository'),
            service('payment_app.repository'),
            service(PaymentWebhookSigner::class),
            service('http_client'),
            service(ClockInterface::class),
            service('logger'),
            service(Connection::class),
        ]);

    $services->set(DeliverPaymentWebhookTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(DeliverPaymentWebhookTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(PaymentWebhookDispatcher::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(PaymentCashierSessionLock::class)
        ->args([service(Connection::class)]);

    $services->set(PaymentCashierService::class)
        ->args([
            service('payment_cashier_config.repository'),
            service('payment_cashier_session.repository'),
            service(PaymentRouteResolver::class),
            service(PaymentMethodRegistry::class),
            service(PaymentOrderService::class),
            service(PaymentOrderStarter::class),
            service(PaymentOrderPresenter::class),
            service(ClockInterface::class),
            service(PaymentCashierSessionLock::class),
        ]);

    $services->set(PaymentContextParser::class);

    $services->set(PaymentCashierController::class)
        ->public()
        ->args([
            service(PaymentAppAuthenticator::class),
            service(PaymentCashierService::class),
            service(PaymentContextParser::class),
        ])
        ->tag('controller.service_arguments');

    // ---- Api: the contract the apps call ----
    // the payment api is not the store api and not the admin api: an external app authenticates itself
    // with the credentials it sends as request parameters, so the scope has to be its own
    $services->set(PaymentApiRouteScope::class)
        ->tag('shopwell.route_scope');

    $services->set(PaymentOrderController::class)
        ->public()
        ->args([
            service(PaymentAppAuthenticator::class),
            service(PaymentOrderService::class),
            service(PaymentOrderStarter::class),
            service(PaymentOrderCloser::class),
            service(PaymentOrderPresenter::class),
            service(PaymentRouteResolver::class),
            service(PaymentContextParser::class),
        ])
        ->tag('controller.service_arguments');

    $services->set(PaymentRefundController::class)
        ->public()
        ->args([
            service(PaymentAppAuthenticator::class),
            service(PaymentOrderService::class),
            service(PaymentRefundService::class),
            service(PaymentOrderPresenter::class),
        ])
        ->tag('controller.service_arguments');

    $services->set(PaymentChannelController::class)
        ->public()
        ->args([
            service(PaymentAppAuthenticator::class),
            service(PaymentConfigResolver::class),
        ])
        ->tag('controller.service_arguments');

    // the interface calls this one, so it is not behind the app authentication
    $services->set(NotifyController::class)
        ->public()
        ->args([
            service(PaymentProviderRegistry::class),
            service(PaymentNotificationProcessor::class),
            service('logger'),
        ])
        ->tag('controller.service_arguments');

    $services->set(ReturnController::class)
        ->public()
        ->args([
            service(PaymentOrderService::class),
            service(PaymentOrderPresenter::class),
        ])
        ->tag('controller.service_arguments');
};

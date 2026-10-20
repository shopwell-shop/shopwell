<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel\PaymentMethodGatewayChannelEntity;
use Shopwell\Core\System\Payment\Api\NotifyController;
use Shopwell\Core\System\Payment\Api\PaymentApiRouteScope;
use Shopwell\Core\System\Payment\Api\PaymentChannelController;
use Shopwell\Core\System\Payment\Api\PaymentOrderController;
use Shopwell\Core\System\Payment\Api\PaymentRefundController;
use Shopwell\Core\System\Payment\Api\PaymentTransferNotifyController;
use Shopwell\Core\System\Payment\Api\ReturnController;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\Api\PaymentAppController;
use Shopwell\Core\System\Payment\App\PaymentAppAuthenticator;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentAppProvisioner;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\App\PaymentSignatureService;
use Shopwell\Core\System\Payment\Command\PaymentProvidersPruneCommand;
use Shopwell\Core\System\Payment\Command\PaymentProvidersValidateCommand;
use Shopwell\Core\System\Payment\Gateway\PaymentCloser;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationEntity;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationProcessor;
use Shopwell\Core\System\Payment\Notification\PaymentNotifyUrlBuilder;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderCapture;
use Shopwell\Core\System\Payment\Order\PaymentOrderCloser;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderPaidHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderPresenter;
use Shopwell\Core\System\Payment\Order\PaymentOrderQueryResultHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderReconciler;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStateHandler;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundEntity;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundService;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundStateHandler;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayCaptureAction;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayCloseAction;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayNotificationExtractor;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayNotifyAction;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayPayParamsDecoder;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayProvider;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayQueryAction;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayRefundAction;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayTransferAction;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayTransferNotificationVerifier;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayTransferQueryAction;
use Shopwell\Core\System\Payment\Provider\Api\PaymentProviderConfigController;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigService;
use Shopwell\Core\System\Payment\Provider\PaymentProviderFactory;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalCaptureAction;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalNotificationExtractor;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalNotifyAction;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalProvider;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalQueryAction;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalRefundAction;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalTransferAction;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalTransferNotificationVerifier;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalTransferQueryAction;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeCaptureAction;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeNotificationExtractor;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeNotifyAction;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeProvider;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeQueryAction;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeRefundAction;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeTransferAction;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeTransferNotificationVerifier;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeTransferQueryAction;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatCaptureAction;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatCloseAction;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatNotificationExtractor;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatNotifyAction;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatPayParamsDecoder;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatProvider;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatQueryAction;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatRefundAction;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatTransferAction;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatTransferNotificationVerifier;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatTransferQueryAction;
use Shopwell\Core\System\Payment\ScheduledTask\ExpirePaymentOrdersTask;
use Shopwell\Core\System\Payment\ScheduledTask\ExpirePaymentOrdersTaskHandler;
use Shopwell\Core\System\Payment\ScheduledTask\ReconcileOpenPaymentOrdersTask;
use Shopwell\Core\System\Payment\ScheduledTask\ReconcileOpenPaymentOrdersTaskHandler;
use Shopwell\Core\System\Payment\ScheduledTask\ReconcileOpenPaymentTransfersTask;
use Shopwell\Core\System\Payment\ScheduledTask\ReconcileOpenPaymentTransfersTaskHandler;
use Shopwell\Core\System\Payment\Security\PaymentGatewayTokenEntity;
use Shopwell\Core\System\Payment\Security\PaymentGatewayTokenStorage;
use Shopwell\Core\System\Payment\Subscription\PaymentMandateEntity;
use Shopwell\Core\System\Payment\Subscription\PaymentPlanEntity;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionCycleEntity;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionEntity;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationProcessor;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationVerifierRegistry;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferReconciler;
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

    // ---- Security: the payum tokens, kept in the database so every node resolves them ----
    // `payment_token` belongs to the checkout (return url jwts, single use, cleaned up on expiry),
    // so the tokens of the gateway get a table of their own
    $services->set(PaymentGatewayTokenEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentGatewayTokenStorage::class)
        ->args([
            service('payment_gateway_token.repository'),
        ]);

    // ---- App: the apps that call the payment api, and their secrets ----
    $services->set(PaymentAppEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentAppProviderEntity::class)
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
        ]);

    // onboards the apps that call the payment api and hands them their secret, so it is an admin
    // endpoint as well - the secret never travels through the generic entity endpoints; the writing
    // itself lives in the provisioner, the controller only translates request and response
    $services->set(PaymentAppProvisioner::class)
        ->args([
            service('payment_app.repository'),
            service('payment_app_provider.repository'),
            service('payment_app_channel.repository'),
            service(PaymentCredentialCipher::class),
            service(PaymentProviderRegistry::class),
            service(PaymentChannelRegistry::class),
            service(ClockInterface::class),
        ]);

    $services->set(PaymentAppController::class)
        ->public()
        ->args([
            service('payment_app.repository'),
            service(PaymentAppProvisioner::class),
            // the answers of the controller carry the merchant account of an app and the parameters of
            // its channels in plain text, so it has to unseal them itself
            service(PaymentCredentialCipher::class),
            service(PaymentProviderConfigService::class),
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
    $services->set(PaypalNotificationExtractor::class);
    $services->set(StripeNotificationExtractor::class);

    // An interface that can hand a returning payer back to the answer it already gave registers the
    // decoder here; an interface without one is simply captured again on a reopen.
    $services->set(AlipayPayParamsDecoder::class);
    $services->set(WechatPayParamsDecoder::class);

    $services->set(PaymentProviderFactory::class);

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
    $services->set(PaymentChannelRegistry::class)
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
            service('payment_app_provider.repository'),
            service('payment_app_channel.repository'),
        ])
        ->tag('console.command');

    // ---- the resolution of one payment ----
    $services->set(PaymentConfigResolver::class)
        ->args([
            service(PaymentProviderRegistry::class),
            service(PaymentChannelRegistry::class),
            service('payment_app_provider.repository'),
            service('payment_provider_config.repository'),
            service('payment_app_channel.repository'),
            service('payment_channel_config.repository'),
            service(PaymentCredentialCipher::class),
        ]);

    // the administration asks this one for the interfaces and their channels, so it sits behind the
    // admin session and the acl instead of the app authentication; the sealing and writing of the
    // merchant configuration lives in the config service
    $services->set(PaymentProviderConfigService::class)
        ->args([
            service('payment_provider_config.repository'),
            service('payment_channel_config.repository'),
            service(PaymentCredentialCipher::class),
        ]);

    $services->set(PaymentProviderConfigController::class)
        ->public()
        ->args([
            service(PaymentProviderRegistry::class),
            service(PaymentProviderConfigService::class),
            service(PaymentCredentialCipher::class),
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

    $services->set(PaypalCaptureAction::class);
    $services->set(PaypalRefundAction::class);
    $services->set(PaypalNotifyAction::class);
    $services->set(PaypalQueryAction::class);
    $services->set(PaypalTransferAction::class)
        ->args([service(PaymentCredentialCipher::class)]);
    $services->set(PaypalTransferQueryAction::class);

    // the api of paypal fetches the certificate a webhook is signed with, so its declaration hands the
    // http client over to it
    $services->set(PaypalProvider::class)
        ->args([
            service(PaypalCaptureAction::class),
            service(PaypalRefundAction::class),
            service(PaypalNotifyAction::class),
            service(PaypalQueryAction::class),
            service(PaypalTransferAction::class),
            service(PaypalTransferQueryAction::class),
            service(PaypalNotificationExtractor::class),
            service(PaypalTransferNotificationVerifier::class),
            service('http_client'),
        ])
        ->tag('shopwell.payment.provider', ['key' => 'paypal', 'priority' => -100]);

    $services->set(StripeCaptureAction::class)
        ->args([service(ClockInterface::class)]);
    $services->set(StripeRefundAction::class);
    $services->set(StripeNotifyAction::class);
    $services->set(StripeQueryAction::class);
    $services->set(StripeTransferAction::class);
    $services->set(StripeTransferQueryAction::class);

    $services->set(StripeProvider::class)
        ->args([
            service(StripeCaptureAction::class),
            service(StripeRefundAction::class),
            service(StripeNotifyAction::class),
            service(StripeQueryAction::class),
            service(StripeTransferAction::class),
            service(StripeTransferQueryAction::class),
            service(StripeNotificationExtractor::class),
            service(StripeTransferNotificationVerifier::class),
        ])
        ->tag('shopwell.payment.provider', ['key' => 'stripe', 'priority' => -100]);

    // ---- Order: the payment order, its capture and its refunds ----
    $services->set(PaymentOrderEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentTransactionEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentRefundEntity::class)
        ->tag('shopwell.entity');

    // the state machines of the payment order and its refunds are only driven through the handlers,
    // so the action names are named exactly once
    $services->set(PaymentOrderStateHandler::class)
        ->args([service(StateMachineRegistry::class)]);

    $services->set(PaymentOrderService::class)
        ->args([
            service('payment_order.repository'),
            service(PaymentConfigResolver::class),
            service(InitialStateIdLoader::class),
            service(PaymentOrderStateHandler::class),
            service(ClockInterface::class),
        ]);

    $services->set(PaymentOrderCapture::class)
        ->args([
            service('payment_transaction.repository'),
            service(PaymentConfigResolver::class),
            service(PaymentOrderService::class),
            service(PaymentNotifyUrlBuilder::class),
            service(ClockInterface::class),
        ]);

    // the gateway side of closing: it talks to the channel and keeps the attempt as a transaction,
    // but it never touches the order - the state machine stays with PaymentOrderService
    $services->set(PaymentCloser::class)
        ->args([
            service('payment_transaction.repository'),
            service(PaymentConfigResolver::class),
            service(ClockInterface::class),
        ]);

    // the orchestration of a close: the channel first, the order afterwards. It sits between the two
    // services that must not know each other, because the reconciliation needs PaymentOrderService
    $services->set(PaymentOrderCloser::class)
        ->args([
            service(PaymentCloser::class),
            service(PaymentOrderService::class),
            service(PaymentOrderReconciler::class),
        ]);

    // the one path a payment order becomes paid: a notification and the reconciliation both write
    // the payment through here, so the two cannot drift apart
    $services->set(PaymentOrderPaidHandler::class)
        ->args([
            service(PaymentOrderService::class),
            service(PaymentWebhookDispatcher::class),
            service('event_dispatcher'),
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
            service(PaymentWebhookDispatcher::class),
            service(PaymentNotifyUrlBuilder::class),
            service(PaymentRefundStateHandler::class),
            service(InitialStateIdLoader::class),
            service('event_dispatcher'),
            service(ClockInterface::class),
        ]);

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
        ]);

    // ---- Webhook: the signed deliveries of what happened to the apps ----
    $services->set(PaymentWebhookDeliveryEntity::class)
        ->tag('shopwell.entity');

    // ---- Checkout: the channel a payment method of the shop collects through ----
    $services->set(PaymentMethodGatewayChannelEntity::class)
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
        ]);

    // the two sides of a transfer that talk to the channel: submitting it, and asking what became of
    // it. They share the service as the only writer of the record and the resolver as the only way to
    // the credentials of the app
    $services->set(PaymentTransferSubmitter::class)
        ->args([
            service(PaymentConfigResolver::class),
            service(PaymentTransferService::class),
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

    $services->set(StripeTransferNotificationVerifier::class)
        ->tag('shopwell.payment.transfer_notification_verifier');

    $services->set(PaypalTransferNotificationVerifier::class)
        ->args([service('http_client')])
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
            service(PaymentOrderCapture::class),
            service(PaymentOrderCloser::class),
            service(PaymentOrderPresenter::class),
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

<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection;

use Psr\Clock\ClockInterface;
use Shopwell\Core\System\Payment\Api\NotifyController;
use Shopwell\Core\System\Payment\Api\PaymentApiRouteScope;
use Shopwell\Core\System\Payment\Api\PaymentChannelController;
use Shopwell\Core\System\Payment\Api\PaymentOrderController;
use Shopwell\Core\System\Payment\Api\PaymentRefundController;
use Shopwell\Core\System\Payment\Api\ReturnController;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\Api\PaymentAppController;
use Shopwell\Core\System\Payment\App\PaymentAppAuthenticator;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentAppProvisioner;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\App\PaymentSignatureService;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationEntity;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationProcessor;
use Shopwell\Core\System\Payment\Notification\PaymentNotifyUrlBuilder;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderCapture;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderPresenter;
use Shopwell\Core\System\Payment\Order\PaymentOrderReconciler;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStateHandler;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundEntity;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundService;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundStateHandler;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayApi;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayCaptureAction;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayNotificationExtractor;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayNotifyAction;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayQueryAction;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayRefundAction;
use Shopwell\Core\System\Payment\Provider\Api\PaymentProviderConfigController;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigService;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Provider\PaymentProviderSchemaReader;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalApi;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalCaptureAction;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalNotificationExtractor;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalNotifyAction;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalQueryAction;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalRefundAction;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeApi;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeCaptureAction;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeNotificationExtractor;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeNotifyAction;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeQueryAction;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeRefundAction;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatApi;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatCaptureAction;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatNotificationExtractor;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatNotifyAction;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatQueryAction;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatRefundAction;
use Shopwell\Core\System\Payment\ScheduledTask\ExpirePaymentOrdersTask;
use Shopwell\Core\System\Payment\ScheduledTask\ExpirePaymentOrdersTaskHandler;
use Shopwell\Core\System\Payment\ScheduledTask\ReconcileOpenPaymentOrdersTask;
use Shopwell\Core\System\Payment\ScheduledTask\ReconcileOpenPaymentOrdersTaskHandler;
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
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * The generic payment gateway: the api the external apps call, the payment interfaces and their
 * channels, and the bookkeeping of everything that happened.
 *
 * A payment interface is data plus two services: its declaration file (`Payment/Provider/Resources/
 * config/*.xml`) describes which credentials a merchant has to type in and which channels the
 * interface offers - and that declaration is what the administration renders - while its payum
 * gateway factory and its notification extractor are the code that talks to it. Adding another
 * interface of the platform is one more block below, an interface of an extension is one block in
 * the extension.
 *
 * @codeCoverageIgnore
 */
return static function (ContainerConfigurator $container): void {
    // the interfaces are not told where to notify the platform by an app but by this url, so an
    // installation behind a proxy can pin it instead of trusting the host header of a request
    $container->parameters()->set('shopwell.payment.notify_base_url', '');

    $services = $container->services();

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
        ])
        ->call('setContainer', [service('service_container')])
        ->tag('controller.service_arguments');

    // ---- Provider: the payment interfaces, their channels and the merchant configuration ----
    $services->set(PaymentProviderConfigEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentChannelConfigEntity::class)
        ->tag('shopwell.entity');

    // ---- the payment interfaces ----
    // One notification extractor per interface; the payum gateway factories of the interfaces live in
    // the Gateway section below, together with the actions they consist of.
    $services->set(AlipayNotificationExtractor::class);
    $services->set(WechatNotificationExtractor::class);
    $services->set(PaypalNotificationExtractor::class);
    $services->set(StripeNotificationExtractor::class);

    // The declaration of the interfaces is the contract with the administration, so it lives in a file
    // and not in this wiring: the credentials and the channels below are read from the xml next to the
    // api of the interface and bound to its services here. An interface of an extension brings its own
    // xml, its own api and its own actions.
    $services->set(PaymentProviderSchemaReader::class);

    $configDir = __DIR__ . '/../Payment/Provider/Resources/config';

    $interfaces = [
        'alipay' => ['file' => $configDir . '/alipay.xml', 'factory' => 'shopwell.payment.gateway.factory.alipay', 'extractor' => AlipayNotificationExtractor::class],
        'wechat' => ['file' => $configDir . '/wechat.xml', 'factory' => 'shopwell.payment.gateway.factory.wechat', 'extractor' => WechatNotificationExtractor::class],
        'paypal' => ['file' => $configDir . '/paypal.xml', 'factory' => 'shopwell.payment.gateway.factory.paypal', 'extractor' => PaypalNotificationExtractor::class],
        'stripe' => ['file' => $configDir . '/stripe.xml', 'factory' => 'shopwell.payment.gateway.factory.stripe', 'extractor' => StripeNotificationExtractor::class],
    ];

    foreach ($interfaces as $name => $interface) {
        $services->set('shopwell.payment.provider.' . $name, PaymentProvider::class)
            ->factory([service(PaymentProviderSchemaReader::class), 'readProvider'])
            ->args([
                $interface['file'],
                service($interface['factory']),
                service($interface['extractor']),
            ])
            ->tag('shopwell.payment.provider');
    }

    $services->set(PaymentProviderRegistry::class)
        ->args([tagged_iterator('shopwell.payment.provider')]);

    // the channels are indexed across the interfaces, and a name may only be used once - an app names
    // the way it wants to be paid and the platform derives the interface (and its credentials) from it
    $services->set(PaymentChannelRegistry::class)
        ->args([service(PaymentProviderRegistry::class)]);

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
    // One payum gateway factory per interface, not per channel: the channels of an interface share the
    // credentials, the api and everything but the call that opens a payment, so the capture action
    // dispatches on the channel itself. The factory is wired with the api class and the actions of its
    // interface - the actions are stateless services, the api is built per payment because it carries
    // the resolved credentials of the very payment.
    $services->set(AlipayCaptureAction::class)
        ->args([service(ClockInterface::class)]);
    $services->set(AlipayRefundAction::class);
    $services->set(AlipayNotifyAction::class);
    $services->set(AlipayQueryAction::class);

    $services->set('shopwell.payment.gateway.factory.alipay', PaymentGatewayFactory::class)
        ->args(['alipay', AlipayApi::class, [
            service(AlipayCaptureAction::class),
            service(AlipayRefundAction::class),
            service(AlipayNotifyAction::class),
            service(AlipayQueryAction::class),
        ]]);

    $services->set(WechatCaptureAction::class);
    $services->set(WechatRefundAction::class);
    $services->set(WechatNotifyAction::class);
    $services->set(WechatQueryAction::class);

    $services->set('shopwell.payment.gateway.factory.wechat', PaymentGatewayFactory::class)
        ->args(['wechat', WechatApi::class, [
            service(WechatCaptureAction::class),
            service(WechatRefundAction::class),
            service(WechatNotifyAction::class),
            service(WechatQueryAction::class),
        ]]);

    $services->set(PaypalCaptureAction::class);
    $services->set(PaypalRefundAction::class);
    $services->set(PaypalNotifyAction::class);
    $services->set(PaypalQueryAction::class);

    // the api of paypal fetches the certificate a webhook is signed with, so its factory hands the
    // http client over to it
    $services->set('shopwell.payment.gateway.factory.paypal', PaymentGatewayFactory::class)
        ->args(['paypal', PaypalApi::class, [
            service(PaypalCaptureAction::class),
            service(PaypalRefundAction::class),
            service(PaypalNotifyAction::class),
            service(PaypalQueryAction::class),
        ], [service('http_client')]]);

    $services->set(StripeCaptureAction::class)
        ->args([service(ClockInterface::class)]);
    $services->set(StripeRefundAction::class);
    $services->set(StripeNotifyAction::class);
    $services->set(StripeQueryAction::class);

    $services->set('shopwell.payment.gateway.factory.stripe', PaymentGatewayFactory::class)
        ->args(['stripe', StripeApi::class, [
            service(StripeCaptureAction::class),
            service(StripeRefundAction::class),
            service(StripeNotifyAction::class),
            service(StripeQueryAction::class),
        ]]);

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
            service(PaymentWebhookDispatcher::class),
            service('event_dispatcher'),
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
            service(PaymentWebhookDispatcher::class),
            service('event_dispatcher'),
        ]);

    // ---- Webhook: the signed deliveries of what happened to the apps ----
    $services->set(PaymentWebhookDeliveryEntity::class)
        ->tag('shopwell.entity');

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

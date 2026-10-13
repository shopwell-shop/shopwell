<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection;

use Psr\Clock\ClockInterface;
use Shopwell\Core\System\Payment\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Controller\NotifyController;
use Shopwell\Core\System\Payment\Controller\PaymentAppAdminController;
use Shopwell\Core\System\Payment\Controller\PaymentChannelController;
use Shopwell\Core\System\Payment\Controller\PaymentOrderController;
use Shopwell\Core\System\Payment\Controller\PaymentProviderAdminController;
use Shopwell\Core\System\Payment\Controller\PaymentRefundController;
use Shopwell\Core\System\Payment\Controller\ReturnController;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentNotificationEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentOrderEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentProviderConfigEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentRefundEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentTransactionEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentWebhookDeliveryEntity;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayGatewayFactory;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayNotificationExtractor;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderFactory;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Provider\PaymentProviderSchemaReader;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalGatewayFactory;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalNotificationExtractor;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeGatewayFactory;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeNotificationExtractor;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatGatewayFactory;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatNotificationExtractor;
use Shopwell\Core\System\Payment\Routing\PaymentApiRouteScope;
use Shopwell\Core\System\Payment\ScheduledTask\DeliverPaymentWebhookTask;
use Shopwell\Core\System\Payment\ScheduledTask\DeliverPaymentWebhookTaskHandler;
use Shopwell\Core\System\Payment\ScheduledTask\ExpirePaymentOrdersTask;
use Shopwell\Core\System\Payment\ScheduledTask\ExpirePaymentOrdersTaskHandler;
use Shopwell\Core\System\Payment\Service\PaymentAppAuthenticator;
use Shopwell\Core\System\Payment\Service\PaymentCaptureService;
use Shopwell\Core\System\Payment\Service\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Service\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Service\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Service\PaymentNotificationService;
use Shopwell\Core\System\Payment\Service\PaymentNotifyUrlBuilder;
use Shopwell\Core\System\Payment\Service\PaymentOrderPresenter;
use Shopwell\Core\System\Payment\Service\PaymentOrderService;
use Shopwell\Core\System\Payment\Service\PaymentRefundService;
use Shopwell\Core\System\Payment\Service\PaymentSignatureService;
use Shopwell\Core\System\Payment\Service\PaymentWebhookService;
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

    // ---- the bookkeeping of the api the apps call ----
    $services->set(PaymentAppEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentAppProviderEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentAppChannelEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentProviderConfigEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentChannelConfigEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentOrderEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentTransactionEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentRefundEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentNotificationEntity::class)
        ->tag('shopwell.entity');

    $services->set(PaymentWebhookDeliveryEntity::class)
        ->tag('shopwell.entity');

    // the payment api is not the store api and not the admin api: an external app authenticates itself
    // with the credentials it sends as request parameters, so the scope has to be its own
    $services->set(PaymentApiRouteScope::class)
        ->tag('shopwell.route_scope');

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

    // ---- the payment interfaces ----
    // One payum gateway factory and one notification extractor per interface, not per channel: the
    // channels of an interface share everything but the call that opens a payment, so the capture
    // action dispatches on the channel itself.
    $services->set(AlipayGatewayFactory::class);
    $services->set(AlipayNotificationExtractor::class);

    $services->set(WechatGatewayFactory::class);
    $services->set(WechatNotificationExtractor::class);

    $services->set(PaypalGatewayFactory::class)
        ->args([service('http_client')]);
    $services->set(PaypalNotificationExtractor::class);

    $services->set(StripeGatewayFactory::class);
    $services->set(StripeNotificationExtractor::class);

    // The declaration of the interfaces is the contract with the administration, so it lives in a file
    // and not in this wiring: the credentials and the channels below are read from the xml next to the
    // api of the interface. An interface of an extension brings its own xml and its own api.
    $services->set(PaymentProviderSchemaReader::class);
    $services->set(PaymentProviderFactory::class)
        ->args([service(PaymentProviderSchemaReader::class)]);

    $configDir = __DIR__ . '/../Payment/Provider/Resources/config';

    $interfaces = [
        'alipay' => ['file' => $configDir . '/alipay.xml', 'factory' => AlipayGatewayFactory::class, 'extractor' => AlipayNotificationExtractor::class],
        'wechat' => ['file' => $configDir . '/wechat.xml', 'factory' => WechatGatewayFactory::class, 'extractor' => WechatNotificationExtractor::class],
        'paypal' => ['file' => $configDir . '/paypal.xml', 'factory' => PaypalGatewayFactory::class, 'extractor' => PaypalNotificationExtractor::class],
        'stripe' => ['file' => $configDir . '/stripe.xml', 'factory' => StripeGatewayFactory::class, 'extractor' => StripeNotificationExtractor::class],
    ];

    foreach ($interfaces as $name => $interface) {
        $services->set('shopwell.payment.provider.' . $name, PaymentProvider::class)
            ->factory([service(PaymentProviderFactory::class), 'create'])
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

    // ---- the orchestration ----
    $services->set(PaymentGatewayFactory::class);

    $services->set(PaymentNotifyUrlBuilder::class)
        ->args([
            service('request_stack'),
            param('shopwell.payment.notify_base_url'),
        ]);

    $services->set(PaymentOrderService::class)
        ->args([
            service('payment_order.repository'),
            service(PaymentConfigResolver::class),
            service(InitialStateIdLoader::class),
            service(StateMachineRegistry::class),
            service(ClockInterface::class),
        ]);

    $services->set(PaymentCaptureService::class)
        ->args([
            service('payment_transaction.repository'),
            service(PaymentGatewayFactory::class),
            service(PaymentConfigResolver::class),
            service(PaymentOrderService::class),
            service(PaymentNotifyUrlBuilder::class),
            service(ClockInterface::class),
        ]);

    $services->set(PaymentRefundService::class)
        ->args([
            service('payment_refund.repository'),
            service(PaymentGatewayFactory::class),
            service(PaymentConfigResolver::class),
            service(PaymentOrderService::class),
            service(PaymentWebhookService::class),
            service(PaymentNotifyUrlBuilder::class),
            service(StateMachineRegistry::class),
            service(InitialStateIdLoader::class),
            service('event_dispatcher'),
            service(ClockInterface::class),
        ]);

    $services->set(PaymentNotificationService::class)
        ->args([
            service('payment_notification.repository'),
            service(PaymentProviderRegistry::class),
            service(PaymentOrderService::class),
            service(PaymentRefundService::class),
            service(PaymentConfigResolver::class),
            service(PaymentGatewayFactory::class),
            service(PaymentWebhookService::class),
            service('event_dispatcher'),
        ]);

    $services->set(PaymentWebhookService::class)
        ->args([
            service('payment_webhook_delivery.repository'),
            service('payment_app.repository'),
            service(PaymentSignatureService::class),
            service(PaymentCredentialCipher::class),
            service('http_client'),
            service(ClockInterface::class),
            service('logger'),
        ]);

    $services->set(PaymentOrderPresenter::class)
        ->args([
            service('state_machine_state.repository'),
        ]);

    // ---- the controllers ----
    $services->set(PaymentOrderController::class)
        ->public()
        ->args([
            service(PaymentAppAuthenticator::class),
            service(PaymentOrderService::class),
            service(PaymentCaptureService::class),
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

    // the administration asks this one for the interfaces and their channels, so it sits behind the
    // admin session and the acl instead of the app authentication
    $services->set(PaymentProviderAdminController::class)
        ->public()
        ->args([
            service(PaymentProviderRegistry::class),
            service('payment_provider_config.repository'),
            service('payment_channel_config.repository'),
            service(PaymentCredentialCipher::class),
        ])
        ->call('setContainer', [service('service_container')])
        ->tag('controller.service_arguments');

    // onboards the apps that call the payment api and hands them their secret, so it is an admin
    // endpoint as well - the secret never travels through the generic entity endpoints
    $services->set(PaymentAppAdminController::class)
        ->public()
        ->args([
            service('payment_app.repository'),
            service('payment_app_provider.repository'),
            service('payment_app_channel.repository'),
            service(PaymentCredentialCipher::class),
            service(PaymentProviderRegistry::class),
            service(PaymentChannelRegistry::class),
        ])
        ->call('setContainer', [service('service_container')])
        ->tag('controller.service_arguments');

    // the interface calls this one, so it is not behind the app authentication
    $services->set(NotifyController::class)
        ->public()
        ->args([
            service(PaymentProviderRegistry::class),
            service(PaymentNotificationService::class),
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

    // ---- the scheduled tasks ----
    $services->set(DeliverPaymentWebhookTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(DeliverPaymentWebhookTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(PaymentWebhookService::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(ExpirePaymentOrdersTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(ExpirePaymentOrdersTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(PaymentOrderService::class),
        ])
        ->tag('messenger.message_handler');
};

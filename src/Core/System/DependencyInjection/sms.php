<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\System\Sms\Api\SmsGatewayProviderController;
use Shopwell\Core\System\Sms\Delivery\SmsDeliveryEntity;
use Shopwell\Core\System\Sms\Gateway\Aliyun\AliyunGatewayTransportFactory;
use Shopwell\Core\System\Sms\Gateway\Log\LogGatewayTransportFactory;
use Shopwell\Core\System\Sms\Gateway\Null\NullGatewayTransportFactory;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayEntity;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayRegistry;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayResolver;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayResolverInterface;
use Shopwell\Core\System\Sms\Gateway\TencentCloud\TencentCloudGatewayTransportFactory;
use Shopwell\Core\System\Sms\Message\SendSmsHandler;
use Shopwell\Core\System\Sms\Service\AbstractSmsSender;
use Shopwell\Core\System\Sms\Service\SmsSender;
use Shopwell\Core\System\Sms\Subscriber\SmsDeliverySubscriber;
use Shopwell\Core\System\Sms\Template\Aggregate\SmsTemplateMapping\SmsTemplateMappingEntity;
use Shopwell\Core\System\Sms\Template\SmsParamValidator;
use Shopwell\Core\System\Sms\Template\SmsTemplateEntity;
use Shopwell\Core\System\Sms\Template\SmsTemplateRenderer;
use Shopwell\Core\System\Sms\Template\SmsTemplateResolver;
use Shopwell\Core\System\Sms\Transport\SmsTransportLoader;
use Shopwell\Core\System\Sms\Util\PhoneNumberNormalizer;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * The multi gateway sms component: one entry point for the business, a gateway that is pickable per
 * sales channel and per country of the recipient and a provider that is pluggable.
 *
 * A provider is one implementation of SmsGatewayTransportFactoryInterface registered with the
 * `shopwell.sms.gateway` tag. The platform ships the null gateway (the baseline that records without
 * sending) and the log gateway (development), an extension adds its provider in its own services.
 *
 * The transports of the text channel are replaced at compile time, see SmsConfigurationCompilerPass.
 *
 * @codeCoverageIgnore
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // ---- the entities ----
    $services->set(SmsGatewayEntity::class)
        ->tag('shopwell.entity');

    $services->set(SmsTemplateEntity::class)
        ->tag('shopwell.entity');

    $services->set(SmsTemplateMappingEntity::class)
        ->tag('shopwell.entity');

    $services->set(SmsDeliveryEntity::class)
        ->tag('shopwell.entity');

    // ---- the gateways ----
    $services->set(NullGatewayTransportFactory::class)
        ->tag('shopwell.sms.gateway');

    $services->set(PhoneNumberNormalizer::class)
        ->args([service(SystemConfigService::class)]);

    $services->set(LogGatewayTransportFactory::class)
        ->args([
            service('logger'),
            service(PhoneNumberNormalizer::class),
        ])
        ->tag('shopwell.sms.gateway');

    // the providers of the platform, they build their client per gateway row from the stored
    // credentials, so they are stateless and take no argument
    $services->set(AliyunGatewayTransportFactory::class)
        ->tag('shopwell.sms.gateway');

    $services->set(TencentCloudGatewayTransportFactory::class)
        ->tag('shopwell.sms.gateway');

    $services->set(SmsGatewayRegistry::class)
        ->args([tagged_iterator('shopwell.sms.gateway')]);

    // tells the administration which providers exist and which credentials they need, so the
    // credential form of a provider - including the one of an extension - is rendered, not shipped
    $services->set(SmsGatewayProviderController::class)
        ->public()
        ->args([
            service(SmsGatewayRegistry::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(SmsGatewayResolver::class)
        ->args([
            service('sms_gateway.repository'),
            service('sms_template_mapping.repository'),
            service(SmsGatewayRegistry::class),
            service(SystemConfigService::class),
        ]);

    $services->alias(SmsGatewayResolverInterface::class, SmsGatewayResolver::class);

    // ---- the transports ----
    $services->set(SmsTransportLoader::class)
        ->args([
            service(SmsGatewayRegistry::class),
            service('sms_gateway.repository'),
            service('logger'),
            service('event_dispatcher')->ignoreOnInvalid(),
        ]);

    // ---- the templates ----
    $services->set(SmsParamValidator::class);
    $services->set(SmsTemplateRenderer::class);

    $services->set(SmsTemplateResolver::class)
        ->args([
            service('sms_template.repository'),
            service('sms_template_mapping.repository'),
            service('sms_gateway.repository'),
            service(SmsGatewayRegistry::class),
            service(SmsGatewayResolverInterface::class),
            service(SmsParamValidator::class),
            service(SmsTemplateRenderer::class),
            service(PhoneNumberNormalizer::class),
        ]);

    // ---- the facade ----
    $services->set(SmsSender::class)
        ->args([
            service('texter.transports'),
            service(SmsTemplateResolver::class),
            service(SmsGatewayResolverInterface::class),
            service(PhoneNumberNormalizer::class),
            service('messenger.default_bus'),
        ]);

    $services->alias(AbstractSmsSender::class, SmsSender::class);

    $services->set(SendSmsHandler::class)
        ->args([service('texter.transports')])
        ->tag('messenger.message_handler');

    $services->set(SmsDeliverySubscriber::class)
        ->args([
            service('sms_delivery.repository'),
            service('sms_gateway.repository'),
            service('sms_template.repository'),
            service(PhoneNumberNormalizer::class),
            service(Connection::class),
            service(ClockInterface::class),
            service('event_dispatcher'),
        ])
        ->tag('kernel.event_subscriber');
};

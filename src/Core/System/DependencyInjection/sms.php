<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\System\Sms\Api\SmsGatewayProviderController;
use Shopwell\Core\System\Sms\Api\SmsTemplateAuditController;
use Shopwell\Core\System\Sms\Delivery\SmsDeliveryEntity;
use Shopwell\Core\System\Sms\Gateway\Aliyun\AliyunGatewayTransportFactory;
use Shopwell\Core\System\Sms\Gateway\Audit\SmsGatewayAuditCapabilities;
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
use Shopwell\Core\System\Sms\Template\Audit\SmsTemplateAuditService;
use Shopwell\Core\System\Sms\Template\Audit\SmsTemplateBodyReader;
use Shopwell\Core\System\Sms\Template\SmsParamValidator;
use Shopwell\Core\System\Sms\Template\SmsTemplateEntity;
use Shopwell\Core\System\Sms\Template\SmsTemplateMappingLocator;
use Shopwell\Core\System\Sms\Template\SmsTemplateRenderer;
use Shopwell\Core\System\Sms\Template\SmsTemplateResolver;
use Shopwell\Core\System\Sms\Transport\SmsTransportLoader;
use Shopwell\Core\System\Sms\Util\CountryDialCodeResolver;
use Shopwell\Core\System\Sms\Util\PhoneNumberMasker;
use Shopwell\Core\System\Sms\Util\PhoneNumberMaskerInterface;
use Shopwell\Core\System\Sms\Util\PhoneNumberNormalizer;
use Shopwell\Core\System\Sms\Util\PhoneNumberNormalizerInterface;
use Shopwell\Core\System\Sms\Util\ShippedCountryDialCodeProvider;
use Shopwell\Core\System\Sms\Util\SmsSegmentCounter;
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

    // the dial code data the normaliser reads, a provider is the extension point: a shop that sells
    // into a country the platform ships no code for adds one instead of forking the component
    $services->set(CountryDialCodeResolver::class)
        ->args([tagged_iterator('shopwell.sms.country_dial_code_provider')]);

    $services->set(ShippedCountryDialCodeProvider::class)
        ->tag('shopwell.sms.country_dial_code_provider');

    $services->set(PhoneNumberNormalizer::class)
        ->args([service(CountryDialCodeResolver::class)]);

    $services->alias(PhoneNumberNormalizerInterface::class, PhoneNumberNormalizer::class);

    $services->set(PhoneNumberMasker::class);

    $services->alias(PhoneNumberMaskerInterface::class, PhoneNumberMasker::class);

    $services->set(SmsSegmentCounter::class);

    $services->set(LogGatewayTransportFactory::class)
        ->args([
            service('logger'),
            service(PhoneNumberNormalizerInterface::class),
            service(PhoneNumberMaskerInterface::class),
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

    // the one place that knows which provider declares which audit capability, everything else asks
    // it by technical name instead of comparing a provider name or probing a method
    $services->set(SmsGatewayAuditCapabilities::class)
        ->args([service(SmsGatewayRegistry::class)]);

    // tells the administration which providers exist and which credentials they need, so the
    // credential form of a provider - including the one of an extension - is rendered, not shipped
    $services->set(SmsGatewayProviderController::class)
        ->public()
        ->args([
            service(SmsGatewayRegistry::class),
            service(SmsGatewayAuditCapabilities::class),
        ])
        ->call('setContainer', [service('service_container')]);

    // the three steps of the audit loop of one mapping row, one action per button of the screen
    $services->set(SmsTemplateAuditController::class)
        ->public()
        ->args([
            service(SmsTemplateAuditService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(SmsGatewayResolver::class)
        ->args([
            service('sms_gateway.repository'),
            service('sms_template_mapping.repository'),
            service(SmsGatewayRegistry::class),
            service(SmsGatewayAuditCapabilities::class),
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

    // the audit reports the body of the default language, so the reader is the single source of
    // what a provider sees - the renderer below is the body a free text gateway delivers instead
    $services->set(SmsTemplateBodyReader::class);

    $services->set(SmsTemplateRenderer::class);

    // the single place that resolves the mapping row of a (template, provider, account) combination
    $services->set(SmsTemplateMappingLocator::class)
        ->args([service('sms_template_mapping.repository')]);

    // the audit loop: report a template at the provider, read its state back, confirm it by hand
    $services->set(SmsTemplateAuditService::class)
        ->args([
            service(SmsGatewayAuditCapabilities::class),
            service('sms_template_mapping.repository'),
            service('sms_gateway.repository'),
            service('sms_template.repository'),
            service(SmsTemplateMappingLocator::class),
            service(SmsTemplateBodyReader::class),
            service(Connection::class),
            service(ClockInterface::class),
        ]);

    $services->set(SmsTemplateResolver::class)
        ->args([
            service('sms_template.repository'),
            service(SmsTemplateMappingLocator::class),
            service('sms_gateway.repository'),
            service(SmsGatewayRegistry::class),
            service(SmsGatewayResolverInterface::class),
            service(SmsParamValidator::class),
            service(SmsTemplateRenderer::class),
            service(PhoneNumberNormalizerInterface::class),
        ]);

    // ---- the facade ----
    $services->set(SmsSender::class)
        ->args([
            service('texter.transports'),
            service(SmsTemplateResolver::class),
            service(SmsGatewayResolverInterface::class),
            service(PhoneNumberNormalizerInterface::class),
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
            service(PhoneNumberNormalizerInterface::class),
            service(PhoneNumberMaskerInterface::class),
            service(SmsSegmentCounter::class),
            service(Connection::class),
            service(ClockInterface::class),
            service('event_dispatcher'),
        ])
        ->tag('kernel.event_subscriber');
};

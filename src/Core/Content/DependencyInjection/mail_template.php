<?php declare(strict_types=1);

namespace Shopwell\Core\Content\DependencyInjection;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Content\Mail\Payload\MailPayloadFactory;
use Shopwell\Core\Content\Mail\Service\MailService;
use Shopwell\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Shopwell\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation\MailHeaderFooterTranslationDefinition;
use Shopwell\Core\Content\MailTemplate\Aggregate\MailTemplateMedia\MailTemplateMediaDefinition;
use Shopwell\Core\Content\MailTemplate\Aggregate\MailTemplateTranslation\MailTemplateTranslationDefinition;
use Shopwell\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeDefinition;
use Shopwell\Core\Content\MailTemplate\Aggregate\MailTemplateTypeTranslation\MailTemplateTypeTranslationDefinition;
use Shopwell\Core\Content\MailTemplate\Api\MailActionController;
use Shopwell\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopwell\Core\Content\MailTemplate\Request\Resolver\GetDataAndSendRequestResolver;
use Shopwell\Core\Content\MailTemplate\Request\Resolver\PreviewRequestResolver;
use Shopwell\Core\Content\MailTemplate\Request\Resolver\SimulateRequestResolver;
use Shopwell\Core\Content\MailTemplate\Service\MailDataProvider;
use Shopwell\Core\Content\MailTemplate\Service\MailDataSimulator;
use Shopwell\Core\Content\MailTemplate\Service\MailTemplateContentBuilder;
use Shopwell\Core\Content\MailTemplate\Service\MailTemplateSendService;
use Shopwell\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopwell\Core\Content\Shared\MailFlow\DataProvider\SalesChannelProvider;
use Shopwell\Core\Framework\Adapter\Translation\Translator;
use Shopwell\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\Event\BusinessEventCollector;
use Shopwell\Core\System\Locale\LanguageLocaleCodeProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // Template Entities
    $services->set(MailTemplateDefinition::class)
        ->tag('shopwell.entity.definition', ['entity' => 'mail_template']);

    $services->set(MailTemplateTranslationDefinition::class)
        ->tag('shopwell.entity.definition', ['entity' => 'mail_template_translation']);

    $services->set(MailTemplateTypeDefinition::class)
        ->tag('shopwell.entity.definition', ['entity' => 'mail_template_type']);

    $services->set(MailTemplateTypeTranslationDefinition::class)
        ->tag('shopwell.entity.definition', ['entity' => 'mail_template_type_translation']);

    $services->set(MailTemplateMediaDefinition::class)
        ->tag('shopwell.entity.definition');

    // Header Footer Entities
    $services->set(MailHeaderFooterDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(MailHeaderFooterTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    // Controller
    $services->set(MailActionController::class)
        ->public()
        ->args([
            service(StringTemplateRenderer::class),
            service(MailTemplateService::class),
            service(MailTemplateSendService::class),
            service(MailPayloadFactory::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(MailDataProvider::class)
        ->args([
            tagged_iterator('shopwell.mail.data_provider', 'key'),
        ]);

    $services->set(MailTemplateService::class)
        ->args([
            service('mail_template.repository'),
            service(StringTemplateRenderer::class),
            service(MailDataProvider::class),
            service(MailDataSimulator::class),
            service(MailTemplateContentBuilder::class),
            service('event_dispatcher'),
            service(Translator::class),
            service(LanguageLocaleCodeProvider::class),
        ]);

    $services->set(MailTemplateSendService::class)
        ->args([
            service(MailService::class),
            service(MailDataProvider::class),
        ]);

    $services->set(MailTemplateContentBuilder::class);

    $services->set(MailDataSimulator::class)
        ->args([
            service(BusinessEventCollector::class),
            service(DefinitionInstanceRegistry::class),
            service('event_dispatcher'),
            tagged_iterator('shopwell.mail.data_provider', 'key'),
            service(ClockInterface::class),
        ]);

    $services->set(PreviewRequestResolver::class)
        ->args([
            service(MailTemplateService::class),
            service(SalesChannelProvider::class),
        ])
        ->tag('controller.argument_value_resolver');

    $services->set(GetDataAndSendRequestResolver::class)
        ->args([
            service(MailTemplateService::class),
            service(MailPayloadFactory::class),
        ])
        ->tag('controller.argument_value_resolver');

    $services->set(SimulateRequestResolver::class)
        ->args([
            service(SalesChannelProvider::class),
        ])
        ->tag('controller.argument_value_resolver');
};

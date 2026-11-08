<?php declare(strict_types=1);

namespace Shopwell\Storefront\DependencyInjection;

use Shopwell\Core\Framework\ContentSystem\Adapter\FactoryHelper\DomainAwareLayoutResolver;
use Shopwell\Core\Framework\ContentSystem\Adapter\RenderingSpecificationFactory;
use Shopwell\Core\Framework\ContentSystem\Adapter\RenderingSpecificationResolver;
use Shopwell\Core\Framework\ContentSystem\ContentSection;
use Shopwell\Core\Framework\ContentSystem\Validation\LayoutRootSourceReader;
use Shopwell\Core\System\Dictionary\SalesChannel\DictionaryRoute;
use Shopwell\Storefront\ContentSystem\Extension\ContentLayoutExtension;
use Shopwell\Storefront\ContentSystem\Extension\SalesChannelDomainExtension;
use Shopwell\Storefront\ContentSystem\Extension\SalesChannelExtension;
use Shopwell\Storefront\ContentSystem\FooterContentLayout\FooterContentLayoutDefinition;
use Shopwell\Storefront\ContentSystem\FooterContentLayout\FooterSpecificationSource;
use Shopwell\Storefront\ContentSystem\HeaderContentLayout\HeaderContentLayoutDefinition;
use Shopwell\Storefront\ContentSystem\HeaderContentLayout\HeaderSpecificationSource;
use Shopwell\Storefront\ContentSystem\Hydration\DataLoader\FormGendersDataLoader;
use Shopwell\Storefront\ContentSystem\Hydration\DataLoader\FormGendersLoaderConfigSerializer;
use Shopwell\Storefront\ContentSystem\Validation\HeaderFooterAssignmentWriteValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    // CacheInvalidationSubscriber lives in Core and cannot import these definitions, so the two table names
    // it needs travel as a parameter.
    $containerConfigurator->parameters()
        ->set('shopwell.content_system.section_assignment_entities', [
            HeaderContentLayoutDefinition::ENTITY_NAME => ContentSection::HEADER->value,
            FooterContentLayoutDefinition::ENTITY_NAME => ContentSection::FOOTER->value,
        ]);

    $services = $containerConfigurator->services();

    $services->set(FormGendersDataLoader::class)
        ->args([service(DictionaryRoute::class)])
        ->tag('content_system.data_loader');

    $services->set(FormGendersLoaderConfigSerializer::class)
        ->tag('content_system.config_serializer');

    // Entity Definitions
    $services->set(HeaderContentLayoutDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(FooterContentLayoutDefinition::class)
        ->tag('shopwell.entity.definition');

    // Entity Extensions
    $services->set(SalesChannelExtension::class)
        ->tag('shopwell.entity.extension');

    $services->set(SalesChannelDomainExtension::class)
        ->tag('shopwell.entity.extension');

    $services->set(ContentLayoutExtension::class)
        ->tag('shopwell.entity.extension');

    // Section Resolvers (Header/Footer)
    $services->set('content_system.section_resolver.header', RenderingSpecificationResolver::class)
        ->args([
            [service(HeaderSpecificationSource::class)],
            service(RenderingSpecificationFactory::class),
        ])
        ->tag('content_system.section_resolver', ['section' => 'header']);

    $services->set('content_system.section_resolver.footer', RenderingSpecificationResolver::class)
        ->args([
            [service(FooterSpecificationSource::class)],
            service(RenderingSpecificationFactory::class),
        ])
        ->tag('content_system.section_resolver', ['section' => 'footer']);

    // Domain-Aware Specification Sources
    $services->set(HeaderSpecificationSource::class)
        ->args([
            service(DomainAwareLayoutResolver::class),
            service('header_content_layout.repository'),
        ])
        ->tag('content_system.specification_source', ['section' => 'header']);

    $services->set(FooterSpecificationSource::class)
        ->args([
            service(DomainAwareLayoutResolver::class),
            service('footer_content_layout.repository'),
        ])
        ->tag('content_system.specification_source', ['section' => 'footer']);

    // Header/Footer Binding Gate (§8.2, empty root context)
    $services->set(HeaderFooterAssignmentWriteValidator::class)
        ->args([
            service(LayoutRootSourceReader::class),
        ])
        ->tag('kernel.event_subscriber');
};

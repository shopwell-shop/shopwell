<?php declare(strict_types=1);

namespace Shopwell\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockDefinition;
use Shopwell\Core\Content\Cms\Aggregate\CmsPageTranslation\CmsPageTranslationDefinition;
use Shopwell\Core\Content\Cms\Aggregate\CmsSection\CmsSectionDefinition;
use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotDefinition;
use Shopwell\Core\Content\Cms\Aggregate\CmsSlotTranslation\CmsSlotTranslationDefinition;
use Shopwell\Core\Content\Cms\CmsPageDefinition;
use Shopwell\Core\Content\Cms\DataAbstractionLayer\FieldSerializer\SlotConfigFieldSerializer;
use Shopwell\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopwell\Core\Content\Cms\DataResolver\Element\FormCmsElementResolver;
use Shopwell\Core\Content\Cms\DataResolver\Element\HtmlCmsElementResolver;
use Shopwell\Core\Content\Cms\DataResolver\Element\TextCmsElementResolver;
use Shopwell\Core\Content\Cms\SalesChannel\CmsRoute;
use Shopwell\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoader;
use Shopwell\Core\Content\Cms\Service\CmsFormSlotConfigResolver;
use Shopwell\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Shopwell\Core\Content\Cms\Subscriber\CmsPageDefaultChangeSubscriber;
use Shopwell\Core\Content\Cms\Subscriber\CmsVersionMergeSubscriber;
use Shopwell\Core\Content\Cms\Subscriber\UnusedMediaSubscriber;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Util\HtmlSanitizer;
use Shopwell\Core\System\Salutation\AbstractSalutationsSorter;
use Shopwell\Core\System\Salutation\SalesChannel\SalutationRoute;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CmsPageDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(CmsPageTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(CmsSectionDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(CmsBlockDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(CmsSlotDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(CmsSlotTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(CmsSlotsDataResolver::class)
        ->public()
        ->args([
            tagged_iterator('shopwell.cms.data_resolver'),
            ['product' => service('sales_channel.product.repository')],
            service(DefinitionInstanceRegistry::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(TextCmsElementResolver::class)
        ->args([
            service(HtmlSanitizer::class),
        ])
        ->tag('shopwell.cms.data_resolver');

    $services->set(HtmlCmsElementResolver::class)
        ->tag('shopwell.cms.data_resolver');

    $services->set(FormCmsElementResolver::class)
        ->args([
            service(SalutationRoute::class),
            service(AbstractSalutationsSorter::class),
        ])
        ->tag('shopwell.cms.data_resolver');

    $services->set(SlotConfigFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(SalesChannelCmsPageLoader::class)
        ->args([
            service('cms_page.repository'),
            service(CmsSlotsDataResolver::class),
            service('event_dispatcher'),
            service(CacheTagCollector::class),
        ]);

    $services->set(CmsFormSlotConfigResolver::class)
        ->args([
            service('category.repository'),
            service('landing_page.repository'),
            service('product.repository'),
            service('cms_slot.repository'),
            service(SystemConfigService::class),
        ]);

    $services->set(EntityCmsSlotConfigInheritanceBuilder::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(CmsRoute::class)
        ->public()
        ->args([
            service(SalesChannelCmsPageLoader::class),
        ]);

    $services->set(CmsPageDefaultChangeSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UnusedMediaSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CmsVersionMergeSubscriber::class)
        ->tag('kernel.event_subscriber');
};

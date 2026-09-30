<?php declare(strict_types=1);

namespace Shopwell\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\ProductExport\Api\ProductExportController;
use Shopwell\Core\Content\ProductExport\Command\ProductExportGenerateCommand;
use Shopwell\Core\Content\ProductExport\DataAbstractionLayer\ProductExportExceptionHandler;
use Shopwell\Core\Content\ProductExport\EventListener\ProductExportEventListener;
use Shopwell\Core\Content\ProductExport\ProductExportDefinition;
use Shopwell\Core\Content\ProductExport\Provider\AgenticCommerceProductExportProviderRegistry;
use Shopwell\Core\Content\ProductExport\Provider\GoogleProductExportProvider;
use Shopwell\Core\Content\ProductExport\Provider\OpenAiProductExportProvider;
use Shopwell\Core\Content\ProductExport\SalesChannel\ExportController;
use Shopwell\Core\Content\ProductExport\ScheduledTask\ProductExportGenerateTask;
use Shopwell\Core\Content\ProductExport\ScheduledTask\ProductExportGenerateTaskHandler;
use Shopwell\Core\Content\ProductExport\ScheduledTask\ProductExportPartialGenerationHandler;
use Shopwell\Core\Content\ProductExport\Service\ProductExporter;
use Shopwell\Core\Content\ProductExport\Service\ProductExportFileHandler;
use Shopwell\Core\Content\ProductExport\Service\ProductExportGenerator;
use Shopwell\Core\Content\ProductExport\Service\ProductExportRenderer;
use Shopwell\Core\Content\ProductExport\Service\ProductExportValidator;
use Shopwell\Core\Content\ProductExport\Subscriber\AgenticCommerceProductExportProviderContextSubscriber;
use Shopwell\Core\Content\ProductExport\Validator\FeedLabelValidator;
use Shopwell\Core\Content\ProductExport\Validator\GoogleProductExportValidator;
use Shopwell\Core\Content\ProductExport\Validator\JsonlRowParser;
use Shopwell\Core\Content\ProductExport\Validator\OpenAiProductExportValidator;
use Shopwell\Core\Content\ProductExport\Validator\XmlValidator;
use Shopwell\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopwell\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopwell\Core\Framework\Adapter\Translation\Translator;
use Shopwell\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopwell\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Shopwell\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();
    $parameters->set('product_export.directory', 'export');
    $parameters->set('product_export.read_buffer_size', '%shopwell.product_export.read_buffer_size%');
    // Stale detection tuning: unlock stuck exports when older than max(min_seconds, factor * interval)
    $parameters->set('product_export.stale_min_seconds', 300);
    $parameters->set('product_export.stale_interval_factor', 2.0);

    $services = $containerConfigurator->services();

    $services->set(ProductExportDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(FeedLabelValidator::class)
        ->tag('kernel.event_subscriber');

    $services->set(ProductExportRenderer::class)
        ->args([
            service(StringTemplateRenderer::class),
            service('event_dispatcher'),
        ]);

    $services->set(ProductExporter::class)
        ->public()
        ->args([
            service('product_export.repository'),
            service(ProductExportGenerator::class),
            service('event_dispatcher'),
            service(ProductExportFileHandler::class),
            service(ClockInterface::class),
        ]);

    $services->set(ProductExportFileHandler::class)
        ->args([
            service('shopwell.filesystem.private'),
            param('product_export.directory'),
            service(ClockInterface::class),
        ]);

    $services->set(ProductExportGenerator::class)
        ->public()
        ->args([
            service(ProductStreamBuilder::class),
            service('sales_channel.product.repository'),
            service(ProductExportRenderer::class),
            service('event_dispatcher'),
            service(ProductExportValidator::class),
            service(SalesChannelContextService::class),
            service(Translator::class),
            service(SalesChannelContextPersister::class),
            service(Connection::class),
            param('product_export.read_buffer_size'),
            service(SeoUrlPlaceholderHandlerInterface::class),
            service('twig'),
            service(ProductDefinition::class),
            service(LanguageLocaleCodeProvider::class),
            service(TwigVariableParserFactory::class),
            service(CategoryBreadcrumbBuilder::class),
        ]);

    $services->set(ProductExportGenerateCommand::class)
        ->args([
            service(SalesChannelContextFactory::class),
            service(ProductExporter::class),
        ])
        ->tag('console.command');

    $services->set(ProductExportGenerateTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(ProductExportGenerateTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(Connection::class),
            service('messenger.default_bus'),
            service(ClockInterface::class),
            param('product_export.stale_min_seconds'),
            param('product_export.stale_interval_factor'),
        ])
        ->tag('messenger.message_handler');

    $services->set(ProductExportPartialGenerationHandler::class)
        ->args([
            service(ProductExportGenerator::class),
            service(SalesChannelContextFactory::class),
            service('product_export.repository'),
            service(ProductExportFileHandler::class),
            service('messenger.default_bus'),
            service(ProductExportRenderer::class),
            service(Translator::class),
            service(SalesChannelContextService::class),
            service(SalesChannelContextPersister::class),
            service(Connection::class),
            service(LanguageLocaleCodeProvider::class),
            service(ClockInterface::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(ProductExportController::class)
        ->public()
        ->args([
            service('sales_channel_domain.repository'),
            service('sales_channel.repository'),
            service(ProductExportGenerator::class),
            service('event_dispatcher'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(ProductExportValidator::class)
        ->args([
            tagged_iterator('shopwell.product_export.validator'),
        ]);

    $services->set(JsonlRowParser::class)
        ->tag('shopwell.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(XmlValidator::class)
        ->tag('shopwell.product_export.validator');

    $services->set(OpenAiProductExportValidator::class)
        ->args([
            service(JsonlRowParser::class),
        ])
        ->tag('shopwell.product_export.validator')
        ->tag('shopwell.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(GoogleProductExportValidator::class)
        ->tag('shopwell.product_export.validator')
        ->tag('shopwell.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(AgenticCommerceProductExportProviderRegistry::class)
        ->args([
            tagged_iterator('shopwell.product_export.provider'),
        ])
        ->tag('shopwell.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(OpenAiProductExportProvider::class)
        ->args([
            service('sales_channel.repository'),
            service(SystemConfigService::class),
        ])
        ->tag('shopwell.product_export.provider')
        ->tag('shopwell.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(GoogleProductExportProvider::class)
        ->args([
            service('sales_channel.repository'),
            service(SystemConfigService::class),
        ])
        ->tag('shopwell.product_export.provider')
        ->tag('shopwell.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(ProductExportExceptionHandler::class)
        ->tag('shopwell.dal.exception_handler');

    $services->set(ProductExportEventListener::class)
        ->args([
            service('product_export.repository'),
            service(ProductExportFileHandler::class),
            service('shopwell.filesystem.private'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AgenticCommerceProductExportProviderContextSubscriber::class)
        ->args([
            service(AgenticCommerceProductExportProviderRegistry::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('shopwell.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(ExportController::class)
        ->public()
        ->args([
            service(ProductExporter::class),
            service(ProductExportFileHandler::class),
            service('shopwell.filesystem.private'),
            service('event_dispatcher'),
            service('product_export.repository'),
            service(SalesChannelContextFactory::class),
            service(ClockInterface::class),
        ]);
};

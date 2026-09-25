<?php declare(strict_types=1);

namespace Shopwell\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileDefinition;
use Shopwell\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogDefinition;
use Shopwell\Core\Content\ImportExport\Command\DeleteExpiredFilesCommand;
use Shopwell\Core\Content\ImportExport\Command\ImportEntityCommand;
use Shopwell\Core\Content\ImportExport\Controller\ImportExportActionController;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\CountrySerializer;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\CustomerSerializer;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\EntitySerializer;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\LanguageSerializer;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\MediaSerializer;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\MediaSerializerSubscriber;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\OrderSerializer;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\ProductCrossSellingSerializer;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\ProductSerializer;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\PromotionIndividualCodeSerializer;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\SalutationSerializer;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\CustomFieldsSerializer;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\FieldSerializer;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\PriceSerializer;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\ToOneSerializer;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\TranslationsSerializer;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\PrimaryKeyResolver;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\SystemDefaultValidator;
use Shopwell\Core\Content\ImportExport\Event\Subscriber\CategoryCriteriaSubscriber;
use Shopwell\Core\Content\ImportExport\Event\Subscriber\CustomerNumberRangeSubscriber;
use Shopwell\Core\Content\ImportExport\Event\Subscriber\FileDeletedSubscriber;
use Shopwell\Core\Content\ImportExport\Event\Subscriber\ProductCategoryPathsSubscriber;
use Shopwell\Core\Content\ImportExport\Event\Subscriber\ProductCriteriaSubscriber;
use Shopwell\Core\Content\ImportExport\Event\Subscriber\ProductVariantsSubscriber;
use Shopwell\Core\Content\ImportExport\ImportExportFactory;
use Shopwell\Core\Content\ImportExport\ImportExportProfileDefinition;
use Shopwell\Core\Content\ImportExport\ImportExportProfileTranslationDefinition;
use Shopwell\Core\Content\ImportExport\Message\DeleteFileHandler;
use Shopwell\Core\Content\ImportExport\Message\ImportExportHandler;
use Shopwell\Core\Content\ImportExport\Processing\Pipe\PipeFactory;
use Shopwell\Core\Content\ImportExport\Processing\Reader\CsvReaderFactory;
use Shopwell\Core\Content\ImportExport\Processing\Writer\CsvFileWriterFactory;
use Shopwell\Core\Content\ImportExport\ScheduledTask\CleanupImportExportFileTask;
use Shopwell\Core\Content\ImportExport\ScheduledTask\CleanupImportExportFileTaskHandler;
use Shopwell\Core\Content\ImportExport\Service\CustomerNumberRangeConfigService;
use Shopwell\Core\Content\ImportExport\Service\CustomerNumberRangePatternMatcher;
use Shopwell\Core\Content\ImportExport\Service\DeleteExpiredFilesService;
use Shopwell\Core\Content\ImportExport\Service\DownloadService;
use Shopwell\Core\Content\ImportExport\Service\FileService;
use Shopwell\Core\Content\ImportExport\Service\ImportExportService;
use Shopwell\Core\Content\ImportExport\Service\MappingService;
use Shopwell\Core\Content\ImportExport\Service\SupportedFeaturesService;
use Shopwell\Core\Content\Media\File\FileSaver;
use Shopwell\Core\Content\Media\MediaService;
use Shopwell\Core\Content\Product\ProductTypeRegistry;
use Shopwell\Core\Framework\Api\Sync\SyncService;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\CustomFieldsSerializer as DalCustomFieldsSerializer;
use Shopwell\Core\Framework\RateLimiter\RateLimiter;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\System\CustomField\CustomFieldService;
use Shopwell\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('import_export.supported_entities', ['customer', 'product'])
        ->set('import_export.supported_file_types', ['text/csv'])
        ->set('import_export.read_buffer_size', 100)
        ->set('import_export.write_buffer_size', 100)
        ->set('import_export.http_batch_size', 100);

    $services = $containerConfigurator->services();

    $services->set(ImportExportProfileDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ImportExportLogDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ImportExportFileDefinition::class)
        ->tag('shopwell.entity.definition');

    // @deprecated tag:v6.8.0 Will be removed
    $services->set(ImportExportProfileTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(SystemDefaultValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CustomerNumberRangeSubscriber::class)
        ->args([
            service(CustomerNumberRangeConfigService::class),
            service(CustomerNumberRangePatternMatcher::class),
            service(AbstractIncrementStorage::class),
            service('customer.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CustomerNumberRangePatternMatcher::class);

    $services->set(CustomerNumberRangeConfigService::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ImportExportService::class)
        ->args([
            service('import_export_log.repository'),
            service('user.repository'),
            service('import_export_profile.repository'),
            service(FileService::class),
        ]);

    $services->set(MappingService::class)
        ->args([
            service(FileService::class),
            service('import_export_profile.repository'),
            service(DefinitionInstanceRegistry::class),
            service(ClockInterface::class),
        ]);

    $services->set(FileService::class)
        ->args([
            service('shopwell.filesystem.private'),
            service('import_export_file.repository'),
        ]);

    $services->set(ImportExportActionController::class)
        ->public()
        ->args([
            service(SupportedFeaturesService::class),
            service(ImportExportService::class),
            service(DownloadService::class),
            service('import_export_profile.repository'),
            service(DataValidator::class),
            service(ImportExportFactory::class),
            service(DefinitionInstanceRegistry::class),
            service('messenger.default_bus'),
            service(MappingService::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(SupportedFeaturesService::class)
        ->args([
            param('import_export.supported_entities'),
            param('import_export.supported_file_types'),
        ]);

    $services->set(DownloadService::class)
        ->args([
            service('shopwell.filesystem.private'),
            service('import_export_file.repository'),
            service('logger'),
            param('shopwell.filesystem.private_local_download_strategy'),
            service(RateLimiter::class),
            param('shopwell.filesystem.private_local_path_prefix'),
            service(ClockInterface::class),
        ]);

    $services->set(PrimaryKeyResolver::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(FieldSerializer::class),
        ]);

    $services->set(SerializerRegistry::class)
        ->args([
            tagged_iterator('shopwell.import_export.entity_serializer'),
            tagged_iterator('shopwell.import_export.field_serializer'),
        ]);

    $services->set(EntitySerializer::class)
        ->tag('shopwell.import_export.entity_serializer', ['priority' => -999]);

    $services->set(FieldSerializer::class)
        ->tag('shopwell.import_export.field_serializer', ['priority' => -999]);

    $services->set(ToOneSerializer::class)
        ->args([
            service(PrimaryKeyResolver::class),
        ])
        ->tag('shopwell.import_export.field_serializer', ['priority' => -500]);

    $services->set(TranslationsSerializer::class)
        ->args([
            service('language.repository'),
        ])
        ->tag('shopwell.import_export.field_serializer', ['priority' => -500]);

    $services->set(PriceSerializer::class)
        ->args([
            service('currency.repository'),
        ])
        ->tag('shopwell.import_export.field_serializer', ['priority' => -500]);

    $services->set(CustomFieldsSerializer::class)
        ->args([
            service(DalCustomFieldsSerializer::class),
            service(CustomFieldService::class),
        ])
        ->tag('shopwell.import_export.field_serializer', ['priority' => -500]);

    $services->set(MediaSerializer::class)
        ->args([
            service(MediaService::class),
            service(FileSaver::class),
            service('media_folder.repository'),
            service('media.repository'),
        ])
        ->tag('shopwell.import_export.entity_serializer', ['priority' => -400])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(MediaSerializerSubscriber::class)
        ->args([
            service(MediaSerializer::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SalutationSerializer::class)
        ->args([
            service('salutation.repository'),
        ])
        ->tag('shopwell.import_export.entity_serializer', ['priority' => -400])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(CountrySerializer::class)
        ->args([
            service('country.repository'),
        ])
        ->tag('shopwell.import_export.entity_serializer', ['priority' => -400])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(LanguageSerializer::class)
        ->args([
            service('language.repository'),
        ])
        ->tag('shopwell.import_export.entity_serializer', ['priority' => -400])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(CustomerSerializer::class)
        ->args([
            service('customer_group.repository'),
            service('sales_channel.repository'),
        ])
        ->tag('shopwell.import_export.entity_serializer', ['priority' => -400])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(PromotionIndividualCodeSerializer::class)
        ->args([
            service('promotion_individual_code.repository'),
            service('promotion.repository'),
        ])
        ->tag('shopwell.import_export.entity_serializer', ['priority' => -400])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ProductSerializer::class)
        ->args([
            service('product_visibility.repository'),
            service('sales_channel.repository'),
            service('product_media.repository'),
            service('product_configurator_setting.repository'),
        ])
        ->tag('shopwell.import_export.entity_serializer', ['priority' => -400]);

    $services->set(ProductCrossSellingSerializer::class)
        ->args([
            service('product_cross_selling_assigned_products.repository'),
        ])
        ->tag('shopwell.import_export.entity_serializer', ['priority' => -400]);

    $services->set(OrderSerializer::class)
        ->tag('shopwell.import_export.entity_serializer', ['priority' => -400]);

    $services->set(CsvReaderFactory::class)
        ->tag('shopwell.import_export.reader_factory');

    $services->set(CsvFileWriterFactory::class)
        ->args([
            service('shopwell.filesystem.private'),
        ])
        ->tag('shopwell.import_export.writer_factory');

    $services->set(PipeFactory::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(SerializerRegistry::class),
            service(PrimaryKeyResolver::class),
        ])
        ->tag('shopwell.import_export.pipe_factory');

    $services->set(ImportExportFactory::class)
        ->public()
        ->args([
            service(ImportExportService::class),
            service(DefinitionInstanceRegistry::class),
            service('shopwell.filesystem.private'),
            service('event_dispatcher'),
            service(Connection::class),
            service(FileService::class),
            tagged_iterator('shopwell.import_export.reader_factory'),
            tagged_iterator('shopwell.import_export.writer_factory'),
            tagged_iterator('shopwell.import_export.pipe_factory'),
        ]);

    $services->set(ImportExportHandler::class)
        ->public()
        ->args([
            service('messenger.default_bus'),
            service(ImportExportFactory::class),
            service('event_dispatcher'),
            service(ProductTypeRegistry::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(DeleteExpiredFilesService::class)
        ->args([
            service('import_export_file.repository'),
            service(ClockInterface::class),
        ]);

    $services->set(DeleteExpiredFilesCommand::class)
        ->args([
            service(DeleteExpiredFilesService::class),
        ])
        ->tag('console.command');

    // Message handler
    $services->set(DeleteFileHandler::class)
        ->args([
            service('shopwell.filesystem.private'),
        ])
        ->tag('messenger.message_handler');

    // Subscriber
    $services->set(FileDeletedSubscriber::class)
        ->args([
            service('messenger.default_bus'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CategoryCriteriaSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(ProductCategoryPathsSubscriber::class)
        ->args([
            service('category.repository'),
            service(SyncService::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ProductCriteriaSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(ProductVariantsSubscriber::class)
        ->args([
            service(SyncService::class),
            service(Connection::class),
            service('property_group.repository'),
            service('property_group_option.repository'),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ImportEntityCommand::class)
        ->args([
            service(ImportExportService::class),
            service('import_export_profile.repository'),
            service(ImportExportFactory::class),
            service(Connection::class),
            service('shopwell.filesystem.private'),
            service(ClockInterface::class),
        ])
        ->tag('console.command');

    $services->set(CleanupImportExportFileTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(CleanupImportExportFileTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(DeleteExpiredFilesService::class),
        ])
        ->tag('messenger.message_handler');
};

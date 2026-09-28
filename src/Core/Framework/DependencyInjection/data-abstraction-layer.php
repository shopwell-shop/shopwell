<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Content\Product\SearchKeyword\KeywordLoader;
use Shopwell\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreter;
use Shopwell\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopwell\Core\Framework\Api\Sync\SyncFkResolver;
use Shopwell\Core\Framework\Api\Sync\SyncService;
use Shopwell\Core\Framework\Api\Sync\Telemetry\SyncMetricsInstrumentor;
use Shopwell\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopwell\Core\Framework\DataAbstractionLayer\Command\CreateEntitiesCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Command\CreateHydratorCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Command\CreateMigrationCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Command\DataAbstractionLayerValidateCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Command\RefreshIndexCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\CriteriaFieldsResolver;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\EntityAggregator;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\EntityForeignKeyResolver;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\EntityReader;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\EntitySearcher;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\ConfigJsonFieldAccessorBuilder;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\CustomFieldsAccessorBuilder;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\DefaultFieldAccessorBuilder;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\JsonFieldAccessorBuilder;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\PriceFieldAccessorBuilder;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\CriteriaPartResolver;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\ManyToManyAssociationFieldResolver;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\ManyToOneAssociationFieldResolver;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\OneToManyAssociationFieldResolver;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\TranslationFieldResolver;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\JoinGroupBuilder;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\SchemaBuilder;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionValidator;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityGenerator;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionValidator;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\Facade\RepositoryWriterFacadeHookFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacadeHookFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\BlobFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\BoolFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\CalculatedPriceFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\CartPriceFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\CashRoundingConfigFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\ConfigJsonFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\CreatedAtFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\CreatedByFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\CronIntervalFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\CustomFieldsSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\DateFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\DateIntervalFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\DateTimeFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\EmailFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\EnumFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\FkFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\FloatFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\IdFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\IntFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\ListFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\LongTextFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToManyAssociationFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToOneAssociationFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\OneToManyAssociationFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\OneToOneAssociationFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\PasswordFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\PHPUnserializeFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceDefinitionFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\ReferenceVersionFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\RemoteAddressFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\StateMachineStateFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\StringFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\TaxFreeConfigFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\TimeZoneFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\TranslatedFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\TranslationsAssociationFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\UpdatedAtFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\UpdatedByFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\VariantListingConfigFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\VersionDataPayloadFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\VersionFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\WasModifiedByUserFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\Subscriber\EntityIndexingSubscriber;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\Subscriber\RegisteredIndexerSubscriber;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\Telemetry\IndexerMetricsInstrumentor;
use Shopwell\Core\Framework\DataAbstractionLayer\MigrationFileRenderer;
use Shopwell\Core\Framework\DataAbstractionLayer\MigrationQueryGenerator;
use Shopwell\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\ApiCriteriaValidator;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\CachedCompressedCriteriaDecoder;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\CachedSearchConfigLoader;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\CriteriaArrayConverter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Parser\AggregationParser;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\SearchTermInterpreter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;
use Shopwell\Core\Framework\DataAbstractionLayer\TechnicalNameExceptionHandler;
use Shopwell\Core\Framework\DataAbstractionLayer\Telemetry\DalSearchInstrumentor;
use Shopwell\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver;
use Shopwell\Core\Framework\DataAbstractionLayer\Telemetry\EntityTelemetrySubscriber;
use Shopwell\Core\Framework\DataAbstractionLayer\Validation\EntityExistsValidator;
use Shopwell\Core\Framework\DataAbstractionLayer\Validation\EntityNotExistsValidator;
use Shopwell\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Version\Cleanup\CleanupVersionTask;
use Shopwell\Core\Framework\DataAbstractionLayer\Version\Cleanup\CleanupVersionTaskHandler;
use Shopwell\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriteResultFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\LockValidator;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\ParentRelationValidator;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopwell\Core\Framework\Migration\IndexerQueuer;
use Shopwell\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopwell\Core\Framework\Script\AppContextCreator;
use Shopwell\Core\Framework\Telemetry\Metrics\Config\MetricConfigProvider;
use Shopwell\Core\Framework\Telemetry\Metrics\Meter;
use Shopwell\Core\Framework\Util\HtmlSanitizer;
use Shopwell\Core\System\CustomField\CustomFieldService;
use Shopwell\Core\System\Language\LanguageLoader;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        // @deprecated tag:v6.8.0 Will be removed, it's then always enabled
        ->set('env(SHOPWELL_DBAL_TIMEZONE_SUPPORT_ENABLED)', '0')
        ->set('shopwell.dbal.time_zone_support_enabled', env('SHOPWELL_DBAL_TIMEZONE_SUPPORT_ENABLED')->bool())
        // @deprecated tag:v6.8.0 Will be removed
        ->set('env(SHOPWELL_DBAL_TOKEN_MINIMUM_LENGTH)', '3')
        // @deprecated tag:v6.8.0 Will be removed
        ->set('shopwell.dbal.token_minimum_length', env('SHOPWELL_DBAL_TOKEN_MINIMUM_LENGTH')->int());

    $services = $containerConfigurator->services();

    $services->set(EntityGenerator::class);

    $services->set(CreateEntitiesCommand::class)
        ->args([
            service(EntityGenerator::class),
            service(DefinitionInstanceRegistry::class),
            param('kernel.project_dir'),
        ])
        ->tag('console.command');

    $services->set(CreateMigrationCommand::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(MigrationQueryGenerator::class),
            service('kernel'),
            service(Filesystem::class),
            service(MigrationFileRenderer::class),
            param('kernel.shopwell_core_dir'),
            param('kernel.shopwell_version'),
            service(ClockInterface::class),
        ])
        ->tag('console.command');

    $services->set(SchemaBuilder::class);

    $services->set(MigrationFileRenderer::class);

    $services->set(MigrationQueryGenerator::class)
        ->args([
            service(Connection::class),
            service(SchemaBuilder::class),
        ]);

    $services->set(EntityLoadedEventFactory::class)
        ->public()
        ->args([
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(CreateHydratorCommand::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(Filesystem::class),
            param('kernel.project_dir'),
        ])
        ->tag('console.command');

    $services->set(EntityCacheKeyGenerator::class)
        ->public();

    $services->set(EntityDefinitionQueryHelper::class);

    $services->set(JoinGroupBuilder::class)
        ->public();

    $services->set(EntityHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(DefinitionValidator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(Connection::class),
        ]);

    $services->set(Tokenizer::class)
        ->args([
            service(HtmlSanitizer::class),
            param('shopwell.dbal.token_minimum_length'),
            param('shopwell.search.preserved_chars'),
        ]);

    $services->set(SearchTermInterpreter::class)
        ->args([
            service(Tokenizer::class),
            param('shopwell.dbal.token_minimum_length'),
        ]);

    $services->set(EntityScoreQueryBuilder::class);

    $services->set(ProductSearchTermInterpreter::class)
        ->args([
            service(Connection::class),
            service(Tokenizer::class),
            service('logger'),
            service(TokenFilter::class),
            service(KeywordLoader::class),
            service(SearchConfigLoader::class),
            param('shopwell.product.search_keyword.relevant_keyword_count'),
        ]);

    $services->set(KeywordLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set('api.request_criteria_builder', RequestCriteriaBuilder::class)
        ->args([
            service(AggregationParser::class),
            service(ApiCriteriaValidator::class),
            service(CriteriaArrayConverter::class),
            service(CompressedCriteriaDecoder::class),
            param('shopwell.api.max_limit'),
        ]);

    $services->set(SearchConfigLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(CachedSearchConfigLoader::class)
        ->decorate(SearchConfigLoader::class, null, -1000)
        ->args([
            service(CachedSearchConfigLoader::class . '.inner'),
            service('cache.object'),
        ]);

    $services->set(CriteriaArrayConverter::class)
        ->args([
            service(AggregationParser::class),
        ]);

    $services->set(CompressedCriteriaDecoder::class);

    $services->set(CachedCompressedCriteriaDecoder::class)
        ->decorate(CompressedCriteriaDecoder::class)
        ->args([
            service(CachedCompressedCriteriaDecoder::class . '.inner'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(RequestCriteriaBuilder::class)
        ->args([
            service(AggregationParser::class),
            service(ApiCriteriaValidator::class),
            service(CriteriaArrayConverter::class),
            service(CompressedCriteriaDecoder::class),
            param('shopwell.api.store.max_limit'),
        ]);

    $services->set(ApiCriteriaValidator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(AggregationParser::class);

    $services->set(RepositoryFacadeHookFactory::class)
        ->public()
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(AppContextCreator::class),
            service(RequestCriteriaBuilder::class),
            service(AclCriteriaValidator::class),
        ]);

    $services->set(RepositoryWriterFacadeHookFactory::class)
        ->public()
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(AppContextCreator::class),
            service(SyncService::class),
        ]);

    $services->set(SalesChannelRepositoryFacadeHookFactory::class)
        ->public()
        ->args([
            service(SalesChannelDefinitionInstanceRegistry::class),
            service(RequestCriteriaBuilder::class),
        ]);

    // EntityDefinition[]
    $services->set(EntityReaderInterface::class, EntityReader::class)
        ->public()
        ->args([
            service(Connection::class),
            service(EntityHydrator::class),
            service(EntityDefinitionQueryHelper::class),
            service(SqlQueryParser::class),
            service(CriteriaQueryBuilder::class),
            service('logger'),
            service(CriteriaFieldsResolver::class),
        ]);

    $services->set(CriteriaFieldsResolver::class);

    $services->set(EntityAggregatorInterface::class, EntityAggregator::class)
        ->public()
        ->args([
            service(Connection::class),
            service(EntityDefinitionQueryHelper::class),
            service(DefinitionInstanceRegistry::class),
            service(CriteriaQueryBuilder::class),
            param('shopwell.dbal.time_zone_support_enabled'),
            service(SearchTermInterpreter::class),
            service(EntityScoreQueryBuilder::class),
        ]);

    $services->set(EntitySearcherInterface::class, EntitySearcher::class)
        ->public()
        ->args([
            service(Connection::class),
            service(EntityDefinitionQueryHelper::class),
            service(CriteriaQueryBuilder::class),
        ]);

    $services->set(CriteriaQueryBuilder::class)
        ->args([
            service(SqlQueryParser::class),
            service(EntityDefinitionQueryHelper::class),
            service(SearchTermInterpreter::class),
            service(EntityScoreQueryBuilder::class),
            service(JoinGroupBuilder::class),
            service(CriteriaPartResolver::class),
        ]);

    $services->set(CriteriaPartResolver::class)
        ->args([
            service(Connection::class),
            service(SqlQueryParser::class),
        ]);

    $services->set(EntityWriter::class)
        ->public()
        ->args([
            service(WriteCommandExtractor::class),
            service(EntityForeignKeyResolver::class),
            service(EntityWriteGatewayInterface::class),
            service(LanguageLoader::class),
            service(DefinitionInstanceRegistry::class),
            service(EntityWriteResultFactory::class),
        ]);

    $services->set(EntityWriteResultFactory::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(Connection::class),
        ]);

    $services->set(WriteCommandExtractor::class)
        ->args([
            service(EntityWriteGatewayInterface::class),
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(EntityWriteGatewayInterface::class, EntityWriteGateway::class)
        ->public()
        ->args([
            param('shopwell.dal.batch_size'),
            service(Connection::class),
            service('event_dispatcher'),
            service(ExceptionHandlerRegistry::class),
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(ConstraintBuilder::class);

    $services->set(SqlQueryParser::class)
        ->args([
            service(EntityDefinitionQueryHelper::class),
            service(Connection::class),
        ]);

    $services->set(EntityForeignKeyResolver::class)
        ->args([
            service(Connection::class),
            service(EntityDefinitionQueryHelper::class),
        ]);

    $services->set(ManyToOneAssociationFieldResolver::class)
        ->args([
            service(EntityDefinitionQueryHelper::class),
            service(Connection::class),
        ])
        ->tag('shopwell.field_resolver', ['priority' => -50]);

    $services->set(OneToManyAssociationFieldResolver::class)
        ->tag('shopwell.field_resolver', ['priority' => -50]);

    $services->set(ManyToManyAssociationFieldResolver::class)
        ->tag('shopwell.field_resolver', ['priority' => -50]);

    $services->set(TranslationFieldResolver::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopwell.field_resolver', ['priority' => -50]);

    $services->set(PriceFieldAccessorBuilder::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopwell.field_accessor_builder', ['priority' => -100]);

    $services->set(JsonFieldAccessorBuilder::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopwell.field_accessor_builder', ['priority' => -150]);

    $services->set(DefaultFieldAccessorBuilder::class)
        ->tag('shopwell.field_accessor_builder', ['priority' => -200]);

    $services->set(ConfigJsonFieldAccessorBuilder::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopwell.field_accessor_builder', ['priority' => -100]);

    $services->set(CustomFieldsAccessorBuilder::class)
        ->args([
            service(CustomFieldService::class),
            service(Connection::class),
        ])
        ->tag('shopwell.field_accessor_builder', ['priority' => -100]);

    $services->set(VersionDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(VersionCommitDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(VersionCommitDataDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(RefreshIndexCommand::class)
        ->args([
            service(EntityIndexerRegistry::class),
            service('event_dispatcher'),
            service('messenger.default_bus'),
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('console.command');

    $services->set(RegisteredIndexerSubscriber::class)
        ->args([
            service(IndexerQueuer::class),
            service(EntityIndexerRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(DataAbstractionLayerValidateCommand::class)
        ->args([
            service(DefinitionValidator::class),
        ])
        ->tag('console.command');

    $services->set(VersionManager::class)
        ->public()
        ->args([
            service(EntityWriter::class),
            service(EntityReaderInterface::class),
            service(EntitySearcherInterface::class),
            service(EntityWriteGatewayInterface::class),
            service('event_dispatcher'),
            service('serializer'),
            service(DefinitionInstanceRegistry::class),
            service(VersionCommitDefinition::class),
            service(VersionCommitDataDefinition::class),
            service(VersionDefinition::class),
            service('lock.factory'),
            service(ClockInterface::class),
        ]);

    $services->set(CalculatedPriceFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(CartPriceFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(CashRoundingConfigFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(TaxFreeConfigFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(PriceDefinitionFieldSerializer::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('validator'),
            service(RuleConditionRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(BoolFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(WasModifiedByUserFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(CreatedAtFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
            service(ClockInterface::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(DateFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(DateTimeFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(EmailFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(EnumFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(FkFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(StateMachineStateFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(FloatFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(IdFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(IntFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(RemoteAddressFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
            service(SystemConfigService::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(JsonFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(ConfigJsonFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(LongTextFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
            service(HtmlSanitizer::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(ListFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(ManyToManyAssociationFieldSerializer::class)
        ->args([
            service(WriteCommandExtractor::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(ManyToOneAssociationFieldSerializer::class)
        ->args([
            service(WriteCommandExtractor::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(OneToOneAssociationFieldSerializer::class)
        ->args([
            service(WriteCommandExtractor::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(BlobFieldSerializer::class)
        ->tag('shopwell.field_serializer');

    $services->set(OneToManyAssociationFieldSerializer::class)
        ->args([
            service(WriteCommandExtractor::class),
            service(EntityWriteGatewayInterface::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(PasswordFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
            service(SystemConfigService::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(PHPUnserializeFieldSerializer::class)
        ->tag('shopwell.field_serializer');

    $services->set(PriceFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(VariantListingConfigFieldSerializer::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('validator'),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(ReferenceVersionFieldSerializer::class)
        ->tag('shopwell.field_serializer');

    $services->set(StringFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
            service(HtmlSanitizer::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(TranslatedFieldSerializer::class)
        ->tag('shopwell.field_serializer');

    $services->set(TranslationsAssociationFieldSerializer::class)
        ->args([
            service(WriteCommandExtractor::class),
            service(EntityWriteGatewayInterface::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(UpdatedAtFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
            service(ClockInterface::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(VersionDataPayloadFieldSerializer::class)
        ->tag('shopwell.field_serializer');

    $services->set(VersionFieldSerializer::class)
        ->tag('shopwell.field_serializer');

    $services->set(CustomFieldsSerializer::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('validator'),
            service(CustomFieldService::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(CreatedByFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(UpdatedByFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(TimeZoneFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(CronIntervalFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(DateIntervalFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.field_serializer');

    $services->set(EntityExistsValidator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(EntitySearcherInterface::class),
        ])
        ->tag('validator.constraint_validator');

    $services->set(EntityNotExistsValidator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(EntitySearcherInterface::class),
        ])
        ->tag('validator.constraint_validator');

    $services->set(IteratorFactory::class)
        ->args([
            service(Connection::class),
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(DefinitionInstanceRegistry::class)
        ->public()
        ->args([
            service('service_container'),
            [],
            [],
        ]);

    $services->set(LockValidator::class)
        ->args([
            service(Connection::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ParentRelationValidator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SyncService::class)
        ->public()
        ->args([
            service(EntityWriter::class),
            service('event_dispatcher'),
            service(DefinitionInstanceRegistry::class),
            service(EntitySearcherInterface::class),
            service(RequestCriteriaBuilder::class),
            service(AclCriteriaValidator::class),
            service(SyncFkResolver::class),
            service(SyncMetricsInstrumentor::class),
        ]);

    $services->set(SyncMetricsInstrumentor::class)
        ->args([
            service(Meter::class),
            service(EntityGroupResolver::class),
        ]);

    $services->set(SyncFkResolver::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            tagged_iterator('shopwell.sync.fk_resolver'),
        ]);

    $services->set(ExceptionHandlerRegistry::class)
        ->args([
            tagged_iterator('shopwell.dal.exception_handler'),
        ]);

    $services->set(TechnicalNameExceptionHandler::class)
        ->tag('shopwell.dal.exception_handler');

    $services->set(EntityProtectionValidator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(EntityIndexerRegistry::class)
        ->public()
        ->args([
            tagged_iterator('shopwell.entity_indexer'),
            service('messenger.default_bus'),
            service('event_dispatcher'),
            service(IndexerMetricsInstrumentor::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(IndexerMetricsInstrumentor::class)
        ->args([
            service(Meter::class),
        ]);

    $services->set(EntityIndexingSubscriber::class)
        ->args([
            service(EntityIndexerRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(InheritanceUpdater::class)
        ->args([
            service(Connection::class),
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(ChildCountUpdater::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(Connection::class),
        ]);

    $services->set(ManyToManyIdFieldUpdater::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(Connection::class),
        ]);

    $services->set(CleanupVersionTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(CleanupVersionTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(Connection::class),
            param('shopwell.dal.versioning.expire_days'),
            service(ClockInterface::class),
            service(EventDispatcherInterface::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(EntityTelemetrySubscriber::class)
        ->args([
            service(Meter::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('shopwell.telemetry.subscriber');

    // shared entity-name bucketing for telemetry labels (DAL search collectors + HTTP request domain)
    $services->set(EntityGroupResolver::class);

    // injected into every EntityRepository by EntityCompilerPass; self-gates on the telemetry flag
    $services->set(DalSearchInstrumentor::class)
        ->args([
            service(Meter::class),
            service(EntityGroupResolver::class),
            service(MetricConfigProvider::class),
            param('shopwell.telemetry.metrics.enabled'),
        ]);
};

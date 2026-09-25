<?php declare(strict_types=1);

namespace Shopwell\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterDefinition;
use Shopwell\Core\Content\ProductStream\Aggregate\ProductStreamTranslation\ProductStreamTranslationDefinition;
use Shopwell\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamFilterChangeSetSubscriber;
use Shopwell\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexer;
use Shopwell\Core\Content\ProductStream\ProductStreamDefinition;
use Shopwell\Core\Content\ProductStream\ScheduledTask\UpdateProductStreamMappingTask;
use Shopwell\Core\Content\ProductStream\ScheduledTask\UpdateProductStreamMappingTaskHandler;
use Shopwell\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopwell\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(ProductStreamDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductStreamTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductStreamFilterDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductStreamBuilder::class)
        ->public()
        ->args([
            service('product_stream.repository'),
            service(ProductDefinition::class),
        ]);

    $services->alias(ProductStreamBuilderInterface::class, ProductStreamBuilder::class)
        ->deprecate('shopwell/core', '6.8.0', 'The %alias_id% service is deprecated and will be removed in 6.8.0. Use Shopwell\Core\Content\ProductStream\Service\AbstractProductStreamBuilder instead');

    $services->set(ProductStreamIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('product_stream.repository'),
            service('serializer'),
            service(ProductDefinition::class),
            service('event_dispatcher'),
        ])
        // Must run before ProductIndexer so it compiles stream filters before ProductStreamUpdater creates mappings.
        ->tag('shopwell.entity_indexer', ['priority' => 110]);

    $services->set(ProductStreamFilterChangeSetSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(UpdateProductStreamMappingTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(UpdateProductStreamMappingTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service('product_stream.repository'),
            service('messenger.default_bus'),
        ])
        ->tag('messenger.message_handler');
};

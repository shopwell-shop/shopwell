---
title: Sugar syntax for es definition
issue: NEXT-30040
---
# Core
* Added new class `\Shopwell\Core\Framework\DataAbstractionLayer\Dbal\SqlHelper` to build a select part of a SQL
* Added new class `Shopwell\Elasticsearch\Framework\ElasticsearchFieldBuilder` to help building ES definition mapping
* Added new class `Shopwell\Elasticsearch\Framework\ElasticsearchFieldMapper` to help mapping ES definition data
* Added new class `Shopwell\Elasticsearch\Framework\ElasticsearchIndexingUtils` to provide some utility methods using in ES Definitions
* Added new event `Shopwell\Elasticsearch\Event\ElasticsearchCustomFieldsMappingEvent`
* Deprecated event `Shopwell\Elasticsearch\Product\Event\ElasticsearchProductCustomFieldsMappingEvent` use `ElasticsearchCustomFieldsMappingEvent` instead
* Deprecated these methods in `Shopwell\Elasticsearch\Framework\AbstractElasticsearchDefinition`
    * `stripText`
    * `mapTranslatedField`
    * `mapToManyAssociations`
    * `mapToOneAssociations`
___
# Next Major Version Changes

## New custom fields mapping event

* Previously the event `ElasticsearchProductCustomFieldsMappingEvent` is dispatched when create new ES index so you can add your own custom fields mapping.
* We replaced the event with a new event `Shopwell\Elasticsearch\Event\ElasticsearchCustomFieldsMappingEvent`, this provides a better generic way to add custom fields mapping

```php
class ExampleCustomFieldsMappingEventSubscriber implements EventSubscriberInterface {

    public static function getSubscribedEvents(): array
    {
        return [
            ElasticsearchCustomFieldsMappingEvent::class => 'addCustomFieldsMapping',
        ];
    }

    public function addCustomFieldsMapping(ElasticsearchCustomFieldsMappingEvent $event): void 
    {
        if ($event->getEntity() === 'product') {
            $event->setMapping('productCfFoo', CustomFieldTypes::TEXT);
        }

        if ($event->getEntity() === 'category') {
            $event->setMapping('categoryCfFoo', CustomFieldTypes::TEXT);
        }
        // ...
    }
}
```

## Adding sugar syntax for ES Definition

We added new utility classes to make creating custom ES definition look simpler

In this example, assuming you have a custom ES definition with `name` & `description` fields are translatable fields:

```php
<?php declare(strict_types=1);

namespace Shopwell\Commercial\AdvancedSearch\Domain\Indexing\ElasticsearchDefinition\Manufacturer;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\SqlHelper;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopwell\Elasticsearch\Framework\ElasticsearchFieldBuilder;
use Shopwell\Elasticsearch\Framework\ElasticsearchFieldMapper;
use Shopwell\Elasticsearch\Framework\ElasticsearchIndexingUtils;

class YourElasticsearchDefinition extends AbstractElasticsearchDefinition
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityDefinition $definition,
        private readonly CompletionDefinitionEnrichment $completionDefinitionEnrichment,
        private readonly ElasticsearchFieldBuilder $fieldBuilder
    ) {
    }

    public function getMapping(Context $context): array
    {
        $languageFields = $this->fieldBuilder->translated(self::getTextFieldConfig());

        $properties = [
            'id' => self::KEYWORD_FIELD,
            'name' => $languageFields,
            'description' => $languageFields,
        ];

        return [
            '_source' => ['includes' => ['id']],
            'properties' => $properties,
        ];
    }

    public function fetch(array $ids, Context $context): array
    {
        $data = $this->fetchData($ids, $context);

        $documents = [];

        foreach ($data as $id => $item) {
            $translations = ElasticsearchIndexingUtils::parseJson($item, 'translation');

            $documents[$id] = [
                'id' => $id,
                'name' => ElasticsearchFieldMapper::translated('name', $translations),
                'description' => ElasticsearchFieldMapper::translated('description', $translations),
            ];
        }

        return $documents;
    }
}
```
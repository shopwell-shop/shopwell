---
title: Elasticsearch refactoring
issue: NEXT-12159
---

# Administration

* Changed product stream condition service from exclude to include pattern
    * Replaced `isPropertyInBlacklist` with `isPropertyInAllowList`
    * Replaced `addToGeneralBlacklist` with `addToGeneralAllowList`
    * Replaced `addToEntityBlacklist` with `addToEntityAllowList`
    * Replaced `removeFromGeneralBlacklist` with `removeFromGeneralAllowList`
___

# Core

* Added new method `Shopwell\Core\Content\Test\Product\ProductBuilder:translation`
* Added new method `Shopwell\Core\Framework\Context:addContextState`
* Added new method `Shopwell\Core\Framework\Context:hasContextState`
* Added new method `Shopwell\Core\Framework\Context:removeContextState`
* Added new parameter `$limit` to method `Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory:createIterator`
* Added new parameter `$loggerLevel` to method `Shopwell\Core\Framework\Log\LoggerFactory:createRotating`
___

# Elasticsearch

* Changed IndexCreator to create the alias directly after creation of index when not existing
* Added a own logger for Elasticsearch
  * The file is located at `var/log/elasticsearch.log`
  * Will be rotated every day
* Added new method `Shopwell\Elasticsearch\Framework\AbstractElasticsearchDefinition:fetch`
* Added new method `Shopwell\Elasticsearch\Framework\AbstractElasticsearchDefinition:stripText`
* Added new parameter `$logger` to method `Shopwell\Elasticsearch\Framework\ClientFactory:createClient`
* Added new parameter `$debug` to method `Shopwell\Elasticsearch\Framework\ClientFactory:createClient`
* Added new parameter `$alias` to method `Shopwell\Elasticsearch\Framework\Indexing\IndexCreator:createIndex`
* Added new method `Shopwell\Elasticsearch\Product\ElasticsearchProductDefinition:fetch`
* Removed parameter `$data` from method `Shopwell\Elasticsearch\Test\Product\ElasticsearchProductTest:testStorefrontListing`
* Deprecated `AbstractElasticsearchDefinition::extendCriteria`, use `fetch` instead
* Deprecated `AbstractElasticsearchDefinition::extendEntities`, use `fetch` instead
* Changed `\Shopwell\Elasticsearch\Framework\Indexing\ElasticsearchIndexer` to register it as own message handler and depend no longer on the entity indexer
* Removed method `\Shopwell\Elasticsearch\Framework\Indexing\ElasticsearchIndexer:extendDocuments`
* Removed method `Shopwell\Elasticsearch\Framework\AbstractElasticsearchDefinition:extendCriteria`
* Removed method `Shopwell\Elasticsearch\Framework\AbstractElasticsearchDefinition:extendEntities`
* Removed parameter `$collection` from method `Shopwell\Elasticsearch\Framework\AbstractElasticsearchDefinition:extendDocuments`
* Removed method `Shopwell\Elasticsearch\Framework\AbstractElasticsearchDefinition:buildFullText`
* Removed method `Shopwell\Elasticsearch\Product\ElasticsearchProductDefinition:extendCriteria`
* Removed parameter `$collection` from method `Shopwell\Elasticsearch\Product\ElasticsearchProductDefinition:extendDocuments`
* Removed method `Shopwell\Elasticsearch\Test\Product\ElasticsearchProductTest:testTermsAggregationWithLimit`
* Removed method `Shopwell\Elasticsearch\Test\Product\ElasticsearchProductTest:testTermsAggregationWithSorting`
* Removed method `Shopwell\Elasticsearch\Test\Product\ElasticsearchProductTest:testFilterAggregationWithTerms`
* Removed following class `Shopwell\Elasticsearch\Framework\FullText`

___
# Upgrade Information

## Elasticsearch Refactoring

To improve the performance and reliability of Elasticsearch, we have decided to refactor Elasticsearch in the first iteration in the Storefront only for Product listing and Product searches.
This allows us to create a optimized Elasticsearch index with only required fields selected by an single sql to make the indexing fast as possible.
This also means for extensions, they need to extend the indexing to make their fields searchable.

Here is an simple decoration to add a new random field named `myNewField` to the index. 
For adding more information from the Database you should execute a single query with all document ids (`array_column($documents, 'id'')`) and map the values

```xml
<service id="MyDecorator" decorates="Shopwell\Elasticsearch\Product\ElasticsearchProductDefinition">
    <argument type="service" id="MyDecorator.inner"/>
    <argument type="service" id="dbal_connection"/>
</service>
```

```php
<?php

use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopwell\Elasticsearch\Framework\Indexing\EntityMapper;
use Doctrine\DBAL\Connection;

class MyDecorator extends AbstractElasticsearchDefinition
{
    private AbstractElasticsearchDefinition $productDefinition;
    private Connection $connection;

    public function __construct(AbstractElasticsearchDefinition $productDefinition, Connection $connection)
    {
        $this->productDefinition = $productDefinition;
        $this->connection = $connection;
    }

    public function getEntityDefinition(): EntityDefinition
    {
        return $this->productDefinition->getEntityDefinition();
    }

    public function getMapping(Context $context): array
    {
        $mapping = $this->productDefinition->getMapping($context);

        $mapping['properties']['myNewField'] = EntityMapper::INT_FIELD;

        // Adding nested field with id
        $mapping['properties']['myManyToManyAssociation'] = [
            'type' => 'nested',
            'properties' => [
                'id' => EntityMapper::KEYWORD_FIELD,
            ],
        ];

        return $mapping;
    }

    public function fetch(array $ids, Context $context): array
    {
        $documents = $this->productDefinition->fetch($ids, $context);

        $query = <<<'SQL'
SELECT LOWER(HEX(mytable.product_id)) as id, GROUP_CONCAT(LOWER(HEX(mytable.myFkField)) SEPARATOR "|") as relationIds
FROM mytable
WHERE
    mytable.product_id IN(:ids) AND
    mytable.product_version_id = :liveVersion
SQL;


        $associationData = $this->connection->fetchAllKeyValue(
            $query,
            [
                'ids' => $ids,
                'liveVersion' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)
            ],
            [
                'ids' => Connection::PARAM_STR_ARRAY
            ]
        );

        foreach ($documents as &$document) {
            // Normal field directly on the product
            $document['myNewField'] = random_int(PHP_INT_MIN, PHP_INT_MAX);

            // Nested object with an id field
            $document['myManyToManyAssociation'] = array_map(function (string $id) {
                return ['id' => $id];
            }, array_filter(explode('|', $associationData[$document['id']] ?? '')));
        }

        return $documents;
    }
}
```

When searching products make sure you add elasticsearch aware to your criteria to use Elasticsearch in background.

```php
$criteria = new \Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria();
$context = \Shopwell\Core\Framework\Context::createDefaultContext();
// Enables elasticsearch for this search
$context->addState(\Shopwell\Core\Framework\Context::STATE_ELASTICSEARCH_AWARE);

$repository->search($criteria, $context);
```

---
title: Use domain exception in Elasticsearch bundle
issue: NEXT-31012 
---
# Core

* Added `\Shopwell\Elasticsearch\ElasticsearchException` as factory class for all Elasticsearch exceptions.
* Deprecated `\Shopwell\Elasticsearch\Exception\ElasticsearchIndexingException`, `\Shopwell\Elasticsearch\Exception\NoIndexedDocumentsException`, `\Shopwell\Elasticsearch\Exception\ServerNotAvailableException`, `\Shopwell\Elasticsearch\Exception\UnsupportedElasticsearchDefinitionException` and `\Shopwell\Elasticsearch\Exception\ElasticsearchIndexingException` use `\Shopwell\Elasticsearch\ElasticsearchException` instead.
___
# Next Major Version Changes
## Removal of separate Elasticsearch exception classes
Removed the following exception classes:
* `\Shopwell\Elasticsearch\Exception\ElasticsearchIndexingException`
* `\Shopwell\Elasticsearch\Exception\NoIndexedDocumentsException`
* `\Shopwell\Elasticsearch\Exception\ServerNotAvailableException`
* `\Shopwell\Elasticsearch\Exception\UnsupportedElasticsearchDefinitionException`
* `\Shopwell\Elasticsearch\Exception\ElasticsearchIndexingException`
Use the exception factory class `\Shopwell\Elasticsearch\ElasticsearchException` instead.
---
title: Fix exception thrown by the logic of the ElasticsearchIndexer
issue: NEXT-00000
author: Net Inventors GmbH
author_github: @NetInventors
---
# Core
* Changed method `handleIndexingMessage` in `src/Elasticsearch/Framework/Indexing/ElasticsearchIndexer.php` to throw `Shopwell\Elasticsearch\ElasticsearchException` if the provided message contains no ids for indexing (starting from the version 6.7.0.0).
___
# Next Major Version Changes
## Exception type in ElasticsearchIndexer
Method `Shopwell\Elasticsearch\Framework\Indexing\ElasticsearchIndexer::handleIndexingMessage` will throw `Shopwell\Elasticsearch\ElasticsearchException` if the provided message contains no ids for indexing.
Before, the method was calling OpenSearch client which throws `OpenSearch\Common\Exceptions\BadRequest400Exception` in such case. If your code catches the exception, you need to change the type of the exception to `Shopwell\Elasticsearch\ElasticsearchException`.

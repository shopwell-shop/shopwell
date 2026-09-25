---
title: Fix Elasticsearch indexer usage of unused languages
issue: NEXT-16928
author: Sebastian Seggewiss
author_email: s.seggewiss@shopwell.com 
author_github: seggewiss
---
# Core
* Added `\Shopwell\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexerLanguageCriteriaEvent`
* Added filter to `\Shopwell\Elasticsearch\Framework\Indexing\ElasticsearchIndexer::getLanguages`, to not use unused languages
* Added `\Shopwell\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexerLanguageCriteriaEvent` dispatch to `\Shopwell\Elasticsearch\Framework\Indexing\ElasticsearchIndexer::getLanguages`

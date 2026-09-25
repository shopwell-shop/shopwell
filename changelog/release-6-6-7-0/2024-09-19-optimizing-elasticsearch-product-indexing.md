---
title: Optimizing Elasticsearch product indexing
issue: NEXT-38038
---
# Core
* Changed `\Shopwell\Elasticsearch\Product\ElasticsearchProductDefinition::fetch` to optimize fetching products when doing elasticsearch index
* Added new service `\Shopwell\Core\System\Language\SalesChannelLanguageLoader` to load sales channel languages
---
title: Fix stopwords are not working with AND search
issue: NEXT-37265
---
# Core
* Changed method `\Shopwell\Elasticsearch\TokenQueryBuilder::build` to use dismax instead of bool query at token query level, also use term query for exact match instead of match query
* Changed method `\Shopwell\Elasticsearch\Product\ProductSearchQueryBuilder::build` to build query for whole search term for exact match query beside tokenized queries
* Deprecated `\Shopwell\Elasticsearch\Product\AbstractProductSearchQueryBuilder::build` method to return BuilderInterface instead of BoolQuery in the future
* Deprecated `\Shopwell\Elasticsearch\Framework\AbstractElasticsearchDefinition::buildTermQuery` method to return BuilderInterface instead of BoolQuery in the future

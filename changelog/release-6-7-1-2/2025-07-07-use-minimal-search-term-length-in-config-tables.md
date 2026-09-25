---
title: Use minimal search term length in config tables
issue: 8018
---
# Core
* Changed these classes to load the minimal search term length from the config table and pass it to the Tokenizer.
   * `Shopwell\Core\Content\Product\SearchKeyword\ProductSearchKeywordAnalyzer`
   * `Shopwell\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreter`
   * `Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\SearchTermInterpreter`
   * `Shopwell\Elasticsearch\Product\ProductSearchQueryBuilder`
* Changed `Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter` to use `SearchConfigLoader` to load filter config.
* Changed `load` method in `Shopwell\Elasticsearch\Product\SearchConfigLoader` to load min search length and excluded terms.
* Deprecated parameter `tokenMinimumLength` in `Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer`. This parameter will be removed in v6.8.0.
___
# Upgrade Information
With this change, the minimal search term length is now loaded from the config table instead of being retrieved from the `.env` file.
This allows for more flexible configuration management and ensures that the search functionality adheres to the settings defined in the database.

---
title: Elasticsearch index's mapping should be updated post update
---
# Core
* Changed method `\Shopwell\Elasticsearch\Framework\SystemUpdateListener::__invoke` to call `\Shopwell\Elasticsearch\Framework\Indexing\IndexMappingUpdater::update` post update
* Changed method `\Shopwell\Elasticsearch\Framework\Indexing\IndexMappingUpdater::update` to mark the entities as needs to be re-indexed if the updated mapping is not compatible

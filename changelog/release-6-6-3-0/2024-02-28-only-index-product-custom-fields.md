---
title: Only index product custom fields
issue: NEXT-33613
---

# Core
* Changed `\Shopwell\Elasticsearch\Product\CustomFieldUpdater` to only index product custom fields in Elasticsearch
* Changed `\Shopwell\Elasticsearch\Product\CustomFieldUpdater` to throw a domain exception when trying to add a custom field which already exists in the index with a different type

---
title: Fix elasticsearch update error
issue: NEXT-15503
author: Patrick Weyck
author_email: p.weyck@shopwell.com 
author_github: Patrick Weyck
---
# Core
* Changed `\Shopwell\Elasticsearch\Product\CustomFieldUpdater` to always include the current `_source/includes` data to prevent the `Can't merge because of conflicts: [Cannot update includes setting for [_source]]` error.

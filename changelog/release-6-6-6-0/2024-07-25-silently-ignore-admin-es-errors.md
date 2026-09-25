---
title: Silently ignore admin ES errors
issue: NEXT-37382
---
# Core
* Changed `Shopwell\Elasticsearch\Admin\AdminSearchRegistry` to silently ignore open search exceptions during indexing so that the frontend of the store is not affected when ES is only enabled for the administration 

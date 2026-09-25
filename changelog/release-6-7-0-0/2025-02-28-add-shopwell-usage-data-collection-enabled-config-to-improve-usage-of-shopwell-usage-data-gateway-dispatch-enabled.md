---
title: Add shopwell.usage_data.collection_enabled config to improve usage of shopwell.usage_data.gateway.dispatch_enabled
issue: ANA-217
author: Moritz Krafeld
---
# Core
* Added `shopwell.usage_data.collection_enabled` configuration as a more fine granular configuration for `shopwell.usage_data.gateway.dispatch_enabled` to prevent starting the collection process. This also stops of keeping track of deleted entities.

---
title: Move routing overwrite
issue: NEXT-36479
author: Oliver Skroblin
author_github: OliverSkroblin
---
# Core
* Deprecated `framework.messenger.routing` overwrite logic and moved the config to `shopwell.messenger.routing_overwrite`

___
# Upgrade Information
## Messenger routing overwrite

The overwriting logic for the messenger routing has been moved from `framework.messenger.routing` to `shopwell.messenger.routing_overwrite`. The old config key is still supported but deprecated and will be removed in the next major release.
The new config key considers also the `instanceof` logic of the default symfony behavior.

We have made these changes for various reasons:
1) In the old logic, the `instanceof` logic of Symfony was not taken into account. This means that only exact matches of the class were overwritten.
2) It is not possible to simply backport this in the same config, as not only the project overwrites are in the old config, but also the standard Shopwell routing configs.

```yaml

#before
framework:
    messenger:
        routing:
            Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage: entity_indexing

#after
shopwell:
    messenger:
        routing_overwrite:
            Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage: entity_indexing

```

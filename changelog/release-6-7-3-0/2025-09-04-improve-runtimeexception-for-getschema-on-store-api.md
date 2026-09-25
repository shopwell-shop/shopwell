---
title: Improve RuntimeException for getSchema on store API
issue: 12296
author: Björn Meyer
author_email: b.meyer@shopwell.com
author_github: BrocksiNet
---
___
# API
* Changed the raw exception in `Shopwell\Core\Framework\Api\ApiDefinition\Generator\StoreApiGenerator::getSchema()` with a proper domain exception for `unsupportedStoreApiSchemaEndpoint`.

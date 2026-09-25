---
title: Fix Send mail action change document order version id
issue: NEXT-21032
---
# Core
* Changed method `handle` in `Shopwell\Core\Content\Flow\Dispatching\Action` to use plain SQL to update document's sent field
* Changed method `preview` in `Shopwell\Core\Checkout\Document\DocumentService` to fetch the order data using the correct versionId

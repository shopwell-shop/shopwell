---
title: Update lazy loader in Storable Flow
issue: NEXT-26184
---
# Core
* Added `lazyLoad` functions to replace deprecated `lazy` functions in:
  * `Shopwell\Core\Content\Flow\Dispatching\StorerCustomerGroupStorer`
  * `Shopwell\Core\Content\Flow\Dispatching\CustomerRecoveryStorer`
  * `Shopwell\Core\Content\Flow\Dispatching\CustomerStorer`
  * `Shopwell\Core\Content\Flow\Dispatching\NewsletterRecipientStorer`
  * `Shopwell\Core\Content\Flow\Dispatching\OrderStorer`
  * `Shopwell\Core\Content\Flow\Dispatching\OrderTransactionStorer`
  * `Shopwell\Core\Content\Flow\Dispatching\ProductStorer`
  * `Shopwell\Core\Content\Flow\Dispatching\UserStorer`
* Changed `lazy` method in `Shopwell\Core\Content\Flow\Dispatching\StorableFlow` to correct the lazy loader.

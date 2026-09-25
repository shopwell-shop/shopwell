---
title: Newsletter Email does not change when Customer changes his Email
issue: NEXT-17916
---
# Core
* Changed `CustomerIndexer::update` in `Shopwell\Core\Checkout\Customer\DataAbstractionLayer\CustomerIndexer`. 
* Added new method `updateCustomerEmailRecipient` in `Shopwell\Core\Content\Newsletter\DataAbstractionLayer\Indexing\CustomerNewsletterSalesChannelsUpdater` to update email from newsletter recipient when email customer are updated.

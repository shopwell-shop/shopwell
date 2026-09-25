---
title: Add newsletter.unsubscribe Event
issue: NEXT-14540
---
# Core
* Added `\Shopwell\Core\Content\Newsletter\Event\NewsletterUnsubscribeEvent`
* Changed `\Shopwell\Core\Content\Newsletter\SalesChannel\NewsletterUnsubscribeRoute` to dispatch `NewsletterUnsubscribeEvent`
* Deprecated `\Shopwell\Core\Content\Newsletter\Event\NewsletterUpdateEvent` it will be removed in 6.5.0.0 as it was never thrown.

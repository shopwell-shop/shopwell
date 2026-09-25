---
title: Avoid creating SEO URLs for headless sales channels.
issue: NEXT-30604
flag: v6.6.0.0
author: Andreas Allacher
author_email: andreas.allacher@massiveart.com
author_github: @AndreasA
---
# Core
* Changed method `createUrls` of `Shopwell\Core\Checkout\Customer\Subscriber\CustomerGroupSubscriber` to only retrieve sales channels that are not of the `SALES_CHANNEL_TYPE_API` type for SEO URL generation.
* Changed method `update` of `Shopwell\Core\Content\Seo\SeoUrlUpdater` to only retrieve sales channels that are not of the `SALES_CHANNEL_TYPE_API` type for SEO URL generation.
* Changed method `updateCanonicalUrl` of `Shopwell\Core\Content\Seo\Api\SeoActionController` to silently ignore sales channels that are of the `SALES_CHANNEL_TYPE_API` type for SEO URL update.
* Changed method `createCustomSeoUrls` of `Shopwell\Core\Content\Seo\Api\SeoActionController` to silently ignore sales channels that are of the `SALES_CHANNEL_TYPE_API` type for SEO URL update.

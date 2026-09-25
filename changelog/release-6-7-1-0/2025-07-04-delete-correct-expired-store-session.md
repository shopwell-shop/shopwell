---
title: Delete correct expired store session
issue: #10919
author: Michel Bade
author_email: m.bade@shopwell.com
author_github: @cyl3x
---
# Core
* Changed `Shopwell\Core\Framework\Store\Services\StoreSessionExpiredMiddleware` to delete the user token that the request was sent with, rather than the one from the context.
* Changed `Shopwell\Core\Framework\Store\Services\MiddlewareInterface` to include the `RequestInterface` the request was sent with.
* Changed `Shopwell\Core\Framework\Store\Subscriber\LicenseHostChangedSubscriber` to offload everything regarding In-App Purchases into `InAppPurchaseConfigSubscriber`.
* Added `Shopwell\Core\Framework\Store\InAppPurchase\Subscriber\InAppPurchaseConfigSubscriber` for In-App Purchase JWT invalidations and updates.

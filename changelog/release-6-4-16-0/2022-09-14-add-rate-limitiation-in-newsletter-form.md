---
title: Add rate limitation in newsletter form
issue: NEXT-23156
---
# Core
* Changed function `subscribe` in `Shopwell\Core\Framework\RateLimiter\RateLimiter.php` to check rate limitation.
* Added constant `NEWSLETTER_FORM` in `Shopwell\Core\Framework\RateLimiter\RateLimiter`.
* Changed `Shopwar\Core\Framework\Resources\config\packages\shopwell` to add `rate_limiter` config with `newsletter_form` name.

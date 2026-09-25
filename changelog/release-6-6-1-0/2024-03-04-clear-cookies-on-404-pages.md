---
title: Clear cookies on 404 pages
issue: NEXT-34113
---

# Core

* Changed `\Shopwell\Storefront\Framework\Routing\NotFound\NotFoundSubscriber` to remove all sessions cookies, added by `\Symfony\Component\HttpFoundation\Session\Storage\Handler\AbstractSessionHandler`


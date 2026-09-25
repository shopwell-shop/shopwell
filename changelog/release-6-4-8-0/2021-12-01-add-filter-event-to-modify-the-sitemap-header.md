---
title: Add filter event to modify the sitemap header
issue: NEXT-19239
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Added `Shopwell\Core\Content\Sitemap\Event\SitemapFilterOpenTagEvent` to modify the open tag of the generated sitemap
* Added EventDispatcher for Service `Shopwell\Core\Content\Sitemap\Service\SitemapHandleFactory` in `src/Core/Content/DependencyInjection/sitemap.xml` 
* Changed `Shopwell\Core\Content\Sitemap\Service\SitemapHandle` to dispatch a `SitemapFilterOpenTagEvent`
* Changed `Shopwell\Core\Content\Sitemap\Service\SitemapHandleFactory::__construct` for dependency Injection
* Changed `Shopwell\Core\Content\Test\Sitemap\ServiceSitemapHandleTest`
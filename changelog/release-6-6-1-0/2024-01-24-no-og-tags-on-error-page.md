---
title: no og tags on error pages
issue: NEXT-28228
---
# Storefront
* Changed template `Storefront/Resources/views/storefront/layout/meta.html.twig` to ignore og tags on error pages.
* Added `isErrorPage` method to `Shopwell\Storefront\Framework\Twig\ErrorTemplateStruct`
* Added `isErrorPage` method to `Shopwell\Storefront\Page\Navigation\Error\ErrorPage`

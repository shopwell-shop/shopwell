---
title: Optimize category breadcrumb builder performance
issue: NEXT-15215
author: OliverSkroblin
author_email: o.skroblin@shopwell.com 
author_github: OliverSkroblin
---
# Core
* Changed `\Shopwell\Core\Content\Category\Service\CategoryBreadcrumbBuilder`, to use `product.categoryIds` instead of dynamic join
* Added `\Shopwell\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator::$objects`, which is used to cache hydrated objects


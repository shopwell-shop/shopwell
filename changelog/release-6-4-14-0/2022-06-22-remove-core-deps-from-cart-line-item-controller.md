---
title: Remove core dependencies from CartLineItemController
issue: NEXT-21967
author: Stefan Sluiter
author_email: s.sluiter@shopwell.com
author_github: ssltg
---
# Storefront
* Removed `Shopwell\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface` as a constructor argument from `Shopwell\Storefront\Controller\CartLineItemController`
* Changed `Shopwell\Storefront\Controller\CartLineItemController` to use `Shopwell\Core\Content\Product\SalesChannel\AbstractProductListRoute`
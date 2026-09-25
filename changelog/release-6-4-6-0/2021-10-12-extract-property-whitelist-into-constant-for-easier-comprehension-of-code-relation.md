---
title: Extract property-whitelist into constant for easier comprehension of code relation
issue: NEXT-17955
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Added new constant `\Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM` and used it in `\Shopwell\Core\Content\Product\Cms\ProductListingCmsElementResolver::restrictFilters` to visualize code relation

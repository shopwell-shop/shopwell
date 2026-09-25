---
title: fix seo url criteria for category
issue: NEXT-31086
author: Jeff Böhm
author_email: 3028277+jeboehm@users.noreply.github.com
author_github: jeboehm
---
# Storefront
* Changed `Shopwell\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute::prepareCriteria()` to filter categories of type `CategoryDefinition::TYPE_FOLDER` and type `CategoryDefinition::TYPE_LINK`

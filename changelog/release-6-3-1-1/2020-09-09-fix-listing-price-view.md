---
title:              Fix listing price view
issue:              NEXT-10598
author:             Oliver Skroblin
author_email:       o.skroblin@shopwell.com
author_github:      @OliverSkroblin
---
# Core
* Changed `\Shopwell\Core\Content\Product\ProductEntity::$grouped` flag behavior. The flag is now set over a global event subscriber when a product is loaded in a sales channel context.
* Removed custom handling of `\Shopwell\Core\Content\Product\ProductEntity::$grouped` in `\Shopwell\Core\Content\Product\Cms\ProductSliderCmsElementResolver`
___
# Storefront
* Added `product.isGrouped` check in listing price view

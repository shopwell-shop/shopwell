---
title: Add criteria titles to wishlist Store APIs
issue: NEXT-38728
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Changed return type of `\Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria::setTitle` from `void` to `self`
* Added criteria title `wishlist::load-products` to `\Shopwell\Core\Checkout\Customer\SalesChannel\LoadWishlistRoute::load`
___
# Storefront
* Added criteria title `wishlist::list` to `\Shopwell\Storefront\Controller\WishlistController::ajaxList`
* Added criteria title `wishlist::page` to `\Shopwell\Storefront\Page\Wishlist\WishlistPageLoader::load`

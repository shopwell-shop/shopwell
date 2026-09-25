---
title: Implement wishlist page in storefront
issue: NEXT-11281
---
# Storefront
*  Added `Shopwell\Storefront\Controller\WishlistController`.
*  Added `Shopwell\Storefront\Page\Wishlist\WishlistPage`.
*  Added new page loader `Shopwell\Storefront\Page\Wishlist\WishlistPageLoader` to load `Shopwell\Storefront\Page\Wishlist\WishlistPage`.
*  Added a new event `Shopwell\Storefront\Page\Wishlist\WishlistPageLoaderEvent` to be fired after `Shopwell\Storefront\Page\Wishlist\WishlistPage` is loaded.
*  Added new wishlist page `Shopwell\Storefront\Resources\views\storefront\page\wishlist\index.html.twig`.
*  Added new wishlist pagelet `Shopwell\Storefront\Resources\views\storefront\page\wishlist\wishlist-pagelet.html.twig`.
*  Added new wishlist element `Shopwell\Storefront\Resources\views\storefront\element\cms-element-wishlist-listing.html.twig`.
*  Added new wishlist component listing `Shopwell\Storefront\Resources\views\storefront\component\wishlist\listing.html.twig` to override block `element_product_listing_sorting`.
*  Added new wishlist component action `Shopwell\Storefront\Resources\views\storefront\component\wishlist\card\action.html.twig` to override block `component_product_box_action_detail`.
*  Added new wishlist box product `Shopwell\Storefront\Resources\views\storefront\component\product\card\box-wishlist.html.twig` to override `box-standard.html.twig`.

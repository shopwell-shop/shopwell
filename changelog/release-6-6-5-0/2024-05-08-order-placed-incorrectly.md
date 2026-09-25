---
title: Order placed incorrectly
issue: NEXT-337778
author: Florian Keller
author_email: f.keller@shopwell.com
---
# Core
* Added content hash to `Shopwell\Core\Checkout\Cart` to be able to compare cart content
* Added `Shopwell\Core\Checkout\Cart::CartContextHashService` to handle hashing
* Changed `Shopwell\Core\Checkout\Cart::calculate` to set hash value
___
# Storefront
* Added a hash field to the order confirm form in `storefront/page/checkout/confirm/index.html.twig`
___
# API
* Changed `Shopwell\Core\Checkout\Cart\SalesChannel::order` to accept and handle a content hash

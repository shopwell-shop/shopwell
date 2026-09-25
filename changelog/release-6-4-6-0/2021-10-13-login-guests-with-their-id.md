---
title: Login guests with their ID
issue: NEXT-17934
author: Frederik Schmitt
author_email: f.schmitt@shopwell.com
author_github: fschmtt
---
# Storefront
* Changed `Shopwell\Storefront\Page\Account\Order\AccountOrderPageLoader::load()` to login guests using `Shopwell\Core\Checkout\Customer\SalesChannel\AccountService::loginById()` rather than their email address

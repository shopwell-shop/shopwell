---
title: Consider Storefront validation flag when sending recovery mail
issue: NEXT-19601
author: Jeffrey Boehm
author_github: jeboehm
---
# Core
* Changed `Shopwell\Core\Checkout\Customer\SalesChannel\SendPasswordRecoveryMailRoute` to pass `$validateStorefrontUrl` to the validation method

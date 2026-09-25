---
title: Fix url building in recovery route
issue: NEXT-10398
author: OliverSkroblin
author_email: o.skroblin@shopwell.com 
author_github: OliverSkroblin
---
# Core
* Changed `\Shopwell\Core\Checkout\Customer\SalesChannel\SendPasswordRecoveryMailRoute`, to fix url handling with leading slashes

---
title: Added option to automatically logout guest accounts after order
issue: NEXT-9923
author: Oliver Skroblin
author_email: o.skroblin@shopwell.com 
author_github: OliverSkroblin
---
# Core
* Changed `\Shopwell\Core\Checkout\Customer\SalesChannel\LogoutRoute` behavior, guest session will always be destroyed
* Added config for automatically logout guest account after order complete.

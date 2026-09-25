---
title: Case insensitive guest order e-mail and postal code check
issue: #8686
---
# Core
* Changed `\Shopwell\Core\Checkout\Order\SalesChannel\OrderRoute::checkGuestAuth` to compare guest email address and postalcode case insensitive.

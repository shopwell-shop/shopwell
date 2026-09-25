---
title: Fix not disabled submit order button on cart errors
issue: NEXT-22205
author: Michel Bade
author_email: m.bade@shopwell.com
___
# Storefront
* Removed line to clear cart errors in `src/Storefront/Controller/CheckoutController.php` before rendering the checkout confirm page

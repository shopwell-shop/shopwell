---
title: Wrap Newsletter recipient error in ShopwellException
issue: NEXT-28566
---
# Storefront
* Added `Shopwell\Core\Content\Newsletter\NewsletterException`.
* Changed `\Shopwell\Storefront\Controller\NewsletterController::subscribeMail` to catch recipient error and redirect to frontpage.

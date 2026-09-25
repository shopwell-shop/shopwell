---
title: Customers are able to view document order from Storefront
issue: NEXT-10976
---
# Storefront
*  Added a new Storefront Route named `frontend.account.order.single.document` in `Shopwell\Storefront\Controller\DocumentController` that allows customer to view order's documents from Storefront.
*  Added a new page loader `Shopwell\Storefront\Page\Account\Document\DocumentPageLoader` to load `Shopwell\Storefront\Page\Account\Document\DocumentPage`.
*  Added a new event `Shopwell\Storefront\Page\Account\Document\DocumentPageLoadedEvent` to be fired after `Shopwell\Storefront\Page\Account\Document\DocumentPage` is loaded.

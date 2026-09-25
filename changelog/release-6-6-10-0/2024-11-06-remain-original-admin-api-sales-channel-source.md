---
title: Remain original Admin API sales channel source
issue: NEXT-39173
---
# Core
* Changed `Shopwell\Core\Framework\Api\ControllerSalesChannelProxyController::setUpSalesChannelApiRequest` to use context admin source
* Added request attribute `ATTRIBUTE_CONTEXT_OBJECT` to the `\Shopwell\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters` variable, which is passed to the `\Shopwell\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface` in `\Shopwell\Core\Framework\Routing\SalesChannelRequestContextResolver::resolve` method

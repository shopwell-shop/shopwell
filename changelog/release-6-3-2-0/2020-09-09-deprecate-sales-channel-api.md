---
title: Deprecate Sales Channel API
issue: NEXT-10706
---
# Core

* Deprecated following classes :
    * `Shopwell\Core\Checkout\Cart\SalesChannel\SalesChannelChartController`
    * `Shopwell\Core\Checkout\Cart\SalesChannel\SalesChannelCheckoutController`
    * `Shopwell\Core\Checkout\Customer\SalesChannel\SalesChannelCustomerController`
    * `Shopwell\Core\Content\Cms\SalesChannel\SalesChannelCmsPageController`
    * `Shopwell\Core\Content\Newsletter\SalesChannel\SalesChannelNewsletterController`
    * `Shopwell\Core\Content\Product\SalesChannel\CrossSelling\SalesChannelCrossSellingController`
    * `Shopwell\Core\Framework\Api\Response\Type\SalesChannel\JsonApiType`
    * `Shopwell\Core\Framework\Api\Response\Type\SalesChannel\JsonType`
    * `Shopwell\Core\Framework\Routing\SalesChannelApiRouteScope`
    * `Shopwell\Core\System\SalesChannel\Entity\SalesChannelApiController`
    * `Shopwell\Core\System\SalesChannel\SalesChannel\SalesChannelApiSchemaController`
    * `Shopwell\Core\System\SalesChannel\SalesChannel\SalesChannelContextController`

___
# API

* Deprecated Sales Channel API will be removed with 6.4.0.
    * Use the replacements routes from the Store-API 

___

# Upgrade Information

## Deprecation of the Sales Channel API

As we finished with the implementation of our new Store API, we are deprecating the old Sales Channel API. 
The removal is planned for the 6.4.0.0 release. Projects are using the current Sales Channel API can migrate on api route base. 

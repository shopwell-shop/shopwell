---
title: Extending the app system with shipping methods
issue: NEXT-30229
---

# Core

+ Added the possibility to add new shipping methods via app manifest
* Added new entity `app_shipping_method` in `Shopwell\Core\Framework/App/Aggregate/AppShippingMethod/AppShippingMethodDefinition`
* Added following new classes
    * `Shopwell\Core\Framework\App\Lifecycle\Persister\ShippingMethodPersister`
    * `Shopwell\Core\Framework\App\Manifest\Xml\ShippingMethod\ShippingMethods`
    * `Shopwell\Core\Framework\App\Manifest\Xml\ShippingMethod\ShippingMethod`
    * `Shopwell\Core\Framework\App\Manifest\Xml\ShippingMethod\DeliveryTime`

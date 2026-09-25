---
title: Services stability improvements
issue: NEXT-38322
---
___
# Storefront
* Deprecated `Shopwell\Storefront\Theme\StorefrontPluginRegistry` - It will become internal and not implement `\Shopwell\Storefront\Theme\StorefrontPluginRegistryInterface`
* Deprecated `Shopwell\Storefront\Theme\StorefrontPluginRegistryInterface` - It will be removed without replacement
* Changed `Shopwell\Storefront\Theme\StorefrontPluginRegistry` to ignore services
___
# Upgrade Information
## Internalisation of StorefrontPluginRegistry & Removal of StorefrontPluginRegistryInterface

The class `Shopwell\Storefront\Theme\StorefrontPluginRegistry` will become internal and will no longer implement `Shopwell\Storefront\Theme\StorefrontPluginRegistryInterface`.

The interface `Shopwell\Storefront\Theme\StorefrontPluginRegistryInterface` will be removed.

Please refactor your code to not use this class & interface.
___
## Internalisation of StorefrontPluginRegistry & Removal of StorefrontPluginRegistryInterface

The class `Shopwell\Storefront\Theme\StorefrontPluginRegistry` is now internal and does not implement `Shopwell\Storefront\Theme\StorefrontPluginRegistryInterface`.

The interface `Shopwell\Storefront\Theme\StorefrontPluginRegistryInterface` has been removed.

Please refactor your code to not use this class & interface.

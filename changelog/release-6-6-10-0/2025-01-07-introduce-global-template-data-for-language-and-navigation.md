---
title: Introduce global template data for language and navigation
issue: NEXT-39744
author: Michael Telgmann
author_github: @mitelg
---

# Core
* Deprecated `\Shopwell\Core\System\SalesChannel\Exception\ContextPermissionsLockedException`. Use `\Shopwell\Core\System\SalesChannel\SalesChannelException::contextPermissionsLocked` instead
* Deprecated `\Shopwell\Core\System\SalesChannel\Exception\ContextRulesLockedException`. Use `\Shopwell\Core\System\SalesChannel\SalesChannelException::contextRulesLocked` instead
* Deprecated `\Shopwell\Core\System\Tax\Exception\TaxNotFoundException`. Use `\Shopwell\Core\System\SalesChannel\SalesChannelException::taxNotFound` instead
___

# Storefront
* Added new Twig function `sw_breadcrumb_full_by_id` to get the full breadcrumb for a category ID.
* Added `\Shopwell\Storefront\Framework\Twig\NavigationInfo` to the global `shopwell` Twig variable, to provide the ID of the main navigation and the current navigation path as ID list.
* Added `minSearchLength` to the global `shopwell` Twig variable, which defines the minimum search term length.
* Added `showStagingBanner` to the global `shopwell` Twig variable, which defines if the staging banner should be shown.
* Deprecated the global `showStagingBanner` Twig variable. Use `shopwell.showStagingBanner` instead.
* Deprecated the usage of the `header` and `footer` properties of page Twig objects outside the dedicated header and footer templates. Use the following alternatives instead:
    * `context.currency` instead of `page.header.activeCurrency`
    * `shopwell.navigation.id` instead of `page.header.navigation.active.id`
    * `shopwell.navigation.pathIdList` instead of `page.header.navigation.active.path`
    * `context.languageInfo` instead of `page.header.activeLanguage`
* Added new optional parameter `serviceMenu` of type `\Shopwell\Core\Content\Category\CategoryCollection` to `\Shopwell\Storefront\Pagelet\Footer\FooterPagelet`. It will be required in the next major version.
___

# Upgrade Information

## Deprecation of Twig variable
The global `showStagingBanner` Twig variable has been deprecated. Use `shopwell.showStagingBanner` instead.

## New constructor parameter in FooterPagelet
The new optional parameter `serviceMenu` of type `\Shopwell\Core\Content\Category\CategoryCollection` has been added to `\Shopwell\Storefront\Pagelet\Footer\FooterPagelet`.
You can already add it to your implementation to prevent breaking changes, as it will be required in the next major version.
___

# Next Major Version Changes

## Removal of Twig variable
The global `showStagingBanner` Twig variable was removed. Use `shopwell.showStagingBanner` instead.

## FooterPagelet changes
The former optional parameter `serviceMenu` of type `\Shopwell\Core\Content\Category\CategoryCollection` in `\Shopwell\Storefront\Pagelet\Footer\FooterPagelet` is now required.
Make sure to pass it to the constructor.

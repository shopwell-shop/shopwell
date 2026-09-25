---
title: Load all inherited snippets
issue: NEXT-24159
---

# Storefront
* Changed `Shopwell\Core\System\Snippet\SnippetService` to load all inherited snippets even from level 2 and above inheritances.
* Changed argument `$salesChannelThemeLoader` to `DatabseSalesChannelThemeLoader` in `Shopwell\Core\System\Snippet\SnippetService`
* Changed `Shopwell\Storefront\Theme\Twig\ThemeNamespaceHierarchyBuilder` to use new `DatabaseSalsChannelThemeLoader`.
* Changed argument `$salesChannelThemeLoader` to `DatabaseSalesChannelThemeLoader` from `Shopwell\Storefront\Theme\Twig\ThemeNamespaceHierarchyBuilder`
* Added new abstract class `Shopwell\Storefront\Theme\AbstractSalesChannelThemeLoader`
* Added `Shopwell\Storefront\Theme\DatabaseSalesChannelThemeLoader` as a cachable variant of `Shopwell\Storefront\Theme\SalesChannelThemeLoader`
* Deprecated `\Shopwell\Storefront\Theme\SalesChannelThemeLoader`, use `\Shopwell\Storefront\Theme\DatabaseSalesChannelThemeLoader` instead.

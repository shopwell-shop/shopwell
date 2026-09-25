---
title: Fix storefront show incorrect snippets
issue: NEXT-22670
---
# Core
* Added new `Shopwell\Core\System\Snippet\Files\AbstractSnippetFile` that implements `SnippetFileInterface`
* Deprecated `Shopwell\Core\System\Snippet\Files\SnippetFileInterface`, use `AbstractSnippetFile` instead
* Added a new property `$technicalName` in `Shopwell\Core\System\Snippet\Files\GenericSnippetFile` to show the technical name of the app or plugin that snippets belong to
* Changed method `\Shopwell\Core\System\Snippet\SnippetService::getStorefrontSnippets` to filter out unused themes snippets
___
# Upgrade Information
* Themes snippets are now only applied to Storefront sales channels when they or their child theme are assigned to that sales channel
___
# Next Major Version Changes
* The interface `Shopwell\Core\System\Snippet\Files\SnippetFileInterface` is deprecated, please use `Shopwell\Core\System\Snippet\Files\AbstractSnippetFile` instead .

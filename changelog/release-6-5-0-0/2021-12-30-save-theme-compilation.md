---
title: Save theme compilation
issue: NEXT-15381
---

# Storefront

* Added event `Shopwell\Storefront\Theme\Event\ThemeCopyToLiveEvent`
* Added Exception `Shopwell\Storefront\Theme\Exception\ThemeFileCopyException`
* Changed method `Shopwell\Storefront\Theme\ThemeCompiler::compileTheme` to compile in a temporary directory and only move the new compiled files to live if the compilation was successful.

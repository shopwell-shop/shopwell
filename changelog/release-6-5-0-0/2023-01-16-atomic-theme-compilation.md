---
title: Atomic theme compilation
issue: NEXT-24662
---
# Storefront
* Added `generateNewPath()` and `saveSeed()` methods  in `\Shopwell\Storefront\Theme\AbstractThemePathBuilder`, to allow atomic theme compilations.
* Added `\Shopwell\Storefront\Theme\SeedingThemePathBuilder` as new default implementation for `\Shopwell\Storefront\Theme\AbstractThemePathBuilder`.
* Added `\Shopwell\Storefront\Theme\Message\DeleteThemeFilesMessage` and `\Shopwell\Storefront\Theme\Message\DeleteThemeFilesHandler` to asynchronously delete compiled theme files.
___
# Upgrade Information
## Atomic theme compilation

To allow atomic theme compilations, a seeding mechanism for `AbstractThemePathBuilder` was added.
Whenever a theme is compiled, a new seed is generated and passed to the `generateNewPath()` method, to generate a new theme path with that seed.
After the theme was compiled successfully the `saveSeed()` method is called to that seed, after that subsequent calls to the `assemblePath()` method, must use the newly saved seed for the path generation.

Additionally, the default implementation for `\Shopwell\Storefront\Theme\AbstractThemePathBuilder` was changed from `\Shopwell\Storefront\Theme\MD5ThemePathBuilder` to `\Shopwell\Storefront\Theme\SeedingThemePathBuilder`.

Obsolete compiled theme files are now deleted with a delay, whenever a new theme compilation created new files.
The delay time can be configured in the `shopwell.yaml` file with the new `storefront.theme.file_delete_delay` option, by default it is set to 900 seconds (15 min), if the old theme files should be deleted immediately you can set the value to 0.

For more details refer to the corresponding [ADR](../../adr/storefront/2023-01-10-atomic-theme-compilation.md).
___
# Next Major Version Changes
## Seeding mechanism for `AbstractThemePathBuilder`

The `generateNewPath()` and `saveSeed()` methods  in `\Shopwell\Storefront\Theme\AbstractThemePathBuilder` are now abstract, this means you should implement those methods to allow atomic theme compilations.

For more details refer to the corresponding [ADR](../../adr/storefront/2023-01-10-atomic-theme-compilation.md).

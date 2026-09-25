---
title: Fix ThemeCompiler side effects
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: gecolay
---
# Storefront
* Changed `Shopwell\Storefront\Theme\ThemeCompiler` to use `ThemeFileResolver->resolveStyleFiles` to only calculate required files
* Changed `Shopwell\Storefront\Theme\ThemeRuntimeConfigService` to use `ThemeFileResolver->resolveScriptFiles` to only calculate required files
* Changed `Shopwell\Storefront\Theme\ThemeFileResolver` to public provide `resolveScriptFiles` & `resolveStyleFiles` functions

---
title: Implemented a command to download and install translation
issue: #10491
author: Martin Krzykawski
author_email: m.krzykawki@shopwell.com
author_github: @MartinKrzykawski
---
# Core
* Added the `Shopwell\Core\System\Snippet\Command\InstallTranslationCommand` to download and install translations for all or specific locales.
  * Use `bin/console translation:install` with the argument `--all` to install all available translations or specify a locale with `--locales=en-US,es-ES`.
  * For more information, see the [ADR](https://github.com/shopwell-shop/shopwell/blob/trunk/adr/2025-06-03-integrating-the-language-pack-into-platform.md).
* Deprecated legacy snippet finding in the `Shopwell\Administration\Snippet\SnippetFinder`. The legacy snippet loading will be removed and replaced with the new translation system in v6.8.0.

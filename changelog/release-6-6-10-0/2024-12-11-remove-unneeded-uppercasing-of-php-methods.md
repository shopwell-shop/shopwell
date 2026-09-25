---
title: Remove unneeded uppercasing of PHP methods
issue: NEXT-0000
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Changed `Shopwell\Core\Framework\Adapter\Twig\SwTwigFunction`, `Shopwell\Core\Framework\DataAbstractionLayer\DefinitionValidator` and `Shopwell\Core\Framework\Webhook\BusinessEventEncoder` to not consider explicit casing of methods, as PHP does not consider the case in methods
___
# Storefront
* Changed `Shopwell\Storefront\Theme\ThemeConfigFieldFactory` to not consider explicit casing of methods, as PHP does not consider the case in methods

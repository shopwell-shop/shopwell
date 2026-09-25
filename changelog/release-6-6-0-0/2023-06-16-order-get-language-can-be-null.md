---
title: Allow usage of language property on OrderEntity when not loading the language association
issue: NEXT-31655
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Changed return type of `\Shopwell\Core\Checkout\Order::getLanguage()` to also allow null
* Changed parameter type of `\Shopwell\Core\Checkout\Order::setLanguage()` to also allow null
* Changed PHPdoc type of `\Shopwell\Core\Checkout\Order::$language` to also allow null

---
title: Add Sales channel domain exceptions
issue: NEXT-27207
---
# Core
* Added a new domain exception in `\Shopwell\Core\System\SalesChannel\SalesChannelException`
* Changed `\Shopwell\Core\System\SalesChannel\Context\BaseContextFactory::create` to apply domain exception instead of throw a \RuntimeException

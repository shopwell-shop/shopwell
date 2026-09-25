---
title: Removed ApiAware flag from App in AppPaymentMethod
issue: NEXT-16401
author: Max Stegmeyer
---
# Core
* Removed `app` association from automatically sent payload in `Shopwell\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodDefinition`
* Added filter of `iconRaw` in `jsonSerialize` of `Shopwell\Core\Framework\App\AppEntity` 

---
title: Update salutation default for customer
issue: NEXT-28865
---
# Core
* Added a new migration `Shopwell\Core\Migration\V6_5\Migration1691057865UpdateSalutationDefaultForCustomer` to update salutation default `not_specified`.
* Changed `Shopwell\Core\Checkout\Cart\Address\AddressValidator` to bypass the validator without the salutation
* Deprecated `Shopwell\Core\Checkout\Cart\Address\Error\ProfileSalutationMissingError`

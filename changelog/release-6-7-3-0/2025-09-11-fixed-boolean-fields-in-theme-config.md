---
title: Fix boolean fields in theme config
issue: #11766
---
# Storefront
* Changed `Shopwell\Storefront\Theme\Validator\SCSSValidator` to not fall back to `null` on boolean fields with value `false`.
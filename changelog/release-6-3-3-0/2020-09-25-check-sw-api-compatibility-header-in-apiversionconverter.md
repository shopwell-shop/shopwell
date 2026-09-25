---
title: Check `sw-api-compatibility` header in `ApiVersionConverter`
issue: NEXT-11039
___
# API
* Changed `\Shopwell\Core\Framework\Api\Converter\ApiVersionConverter` to ignore deprecations if the header `sw-api-compatibility` is set. Before this was only checked in the `\Shopwell\Core\Framework\Api\Converter\DefaultApiConverter`. Custom `\Shopwell\Core\Framework\Api\Converter\ApiConverter` had to check it themself.

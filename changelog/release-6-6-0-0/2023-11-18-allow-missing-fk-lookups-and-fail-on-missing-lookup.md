---
title: Allow missing foreign key lookups and fail on missing lookups
author: Joshua Behrens
issue: NEXT-32250
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Added constructor parameters to `\Shopwell\Core\Framework\Api\Sync\FkReference` to get more info on field name, entity name and nullOfMissing behaviour flag
* Changed `\Shopwell\Core\Framework\Api\Sync\AbstractFkResolver::resolve` to allow throwing `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException`
* Added support for `nullOnMissing` and `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException` to `\Shopwell\Core\Content\Product\Api\ProductNumberFkResolver`
___
# API
* Changed API response, when foreign key resolvers fail from "500 Internal Server Error Warning: Undefined array key" to a 404 response
* Added optional boolean parameter `nullOnMissing` (default false) for foreign key resolvers, that controls whether resolution fails or falls back to null

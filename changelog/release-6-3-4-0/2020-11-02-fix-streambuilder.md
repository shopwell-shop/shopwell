---
title: Fix stream builder
issue: NEXT-10946
author: Oliver Skroblin
author_email: o.skroblin@shopwell.com 
author_github: Oliver Skroblin
---
# Core
* Changed `\Shopwell\Core\Content\ProductStream\Service\ProductStreamBuilder`, the class uses now the generated `product_stream.api_filter` column to build the filters
* Changed `\Shopwell\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexer`, the class now considers the `position` field to generate the `api_filter` value
* Added generic `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException`

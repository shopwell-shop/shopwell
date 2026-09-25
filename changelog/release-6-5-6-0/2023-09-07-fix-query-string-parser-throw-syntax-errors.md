---
title: Fix query string parser throw syntax errors
issue: NEXT-30306
---
# Core
* Changed `\Shopwell\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser::fromArray` to avoid throwing syntax error when sending query without `queries` key

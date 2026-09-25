---
title: Fix mysql replica parameters parsing
issue: https://github.com/shopwell-shop/shopwell/issues/11085
---
# Core
* Changed `Shopwell\Core\Framework\Adapter\Database\MySQLFactory` to properly initialize replica parameters provided in the dsn format

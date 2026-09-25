---
title: Add missing GROUP BY to EntityReader
issue: NEXT-38050
---

# Core

* Changed `\Shopwell\Core\Framework\DataAbstractionLayer\Dbal\EntityReader::fetch` to add `GROUP BY` on a to-many association query, to fix a MySQL 8 aggregation exception.

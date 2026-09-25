---
title: Improve DAL Criteria ID validation
issue: NEXT-29507
---
# Core
* Added `\Shopwell\Core\Framework\DataAbstractionLayer\InvalidCriteriaIdsException`, to be thrown when the id format for a criteria is invalid
* Changed `\Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria` to validate the format of the given ids in order to prevent issues further down the stack

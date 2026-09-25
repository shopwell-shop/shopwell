---
title: Improve aggregation name validation
issue: NEXT-37397
---

# Core

* Changed `\Shopwell\Core\Framework\DataAbstractionLayer\Search\Parser\AggregationParser` to validate that the aggregation name does not contain question marks or colon,

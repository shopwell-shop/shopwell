---
title: Validate datetime field before build score query in DAL
issue: NEXT-10861
---
# Core
* Added a new private method `validateDateFormat` in `Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder`.
* Updated method `buildScoreQueries` in `Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder` to validate DateField to build ScoreQuery or not.

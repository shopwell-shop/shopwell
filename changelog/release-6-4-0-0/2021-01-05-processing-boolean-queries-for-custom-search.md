---
title: Processing boolean queries for Custom Search
issue: NEXT-13055
---
# Core
*  Added new public constant property `BOOLEAN_CLAUSE_AND` in `Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern` to define possible value for `$booleanClause`
*  Added new public constant property `BOOLEAN_CLAUSE_OR` in `Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern` to define possible value for `$booleanClause`
*  Added new public method `setBooleanClause` in `Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern` to set `$booleanClause`
*  Added new public method `getBooleanClause` in `Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern` to get `$booleanClause`
*  Added new public method `setTokenTerms` in `Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern` to set `$tokenTerms`
*  Added new public method `getTokenTerms` in `Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern` to get `$tokenTerms`

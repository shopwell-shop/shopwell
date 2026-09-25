---
title: Use AbstractTokenFilter in StopwordTokenFilterr
issue: NEXT-31263
---
# Core
* Changed dependency of `\Shopwell\Elasticsearch\Product\StopwordTokenFilter` from `\Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter` to `\Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter` to allow token filter decorators works when adding elasticsearch bundle

---
title: Allow tokenizer decorators wihth elasticsearch package
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
issue: NEXT-31263
---
# Core
* Changed dependency of `\Shopwell\Elasticsearch\Product\ProductSearchQueryBuilder` from `\Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer` to `\Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\TokenizerInterface` to allow tokenizer decorators work when adding elasticsearch bundle

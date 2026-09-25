---
title: Fix generate keywords with special character
issue: NEXT-14649
---
# Core
* Changed `tokenize` function on `Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer` class to change regex to allow (-) symbol when generate keywords.

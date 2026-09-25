---
title: Fix the number slops to find numbers between non-digits
issue: NEXT-40382
author: Björn Meyer
author_email: b.meyer@shopwell.com
author_github: @BrocksiNet
---
# Core
* Changed `\Shopwell\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreter::slop` to also find the number between non-digits.

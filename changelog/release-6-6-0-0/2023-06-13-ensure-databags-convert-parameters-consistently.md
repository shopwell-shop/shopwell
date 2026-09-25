---
title: Ensure databags convert parameters consistently
issue: NEXT-31821
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Changed `\Shopwell\Core\Framework\Validation\DataBag\DataBag::set` and `\Shopwell\Core\Framework\Validation\DataBag\DataBag::add` to convert arrays to `\Shopwell\Core\Framework\Validation\DataBag\DataBag` like the constructor
* Added `\Shopwell\Core\Framework\Validation\DataBag\DataBag::__clone` to deep clone

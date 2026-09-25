---
title: Update MatchAllLineItemsRule to support multiple item types
issue: 10720
author: Lars Kemper
author_email: l.kemper@shopwell.com
author_github: @larskemper
---
# Core
* Changed `Shopwell\Core\Framework\Rule\Container\MatchAllLineItemsRule` to support multiple line item types by changing the `$type` property and its corresponding constraint to an `array` and renaming the property to `$types`.

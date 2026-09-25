---
title: fix insufficient rule condition unit value rounding
issue: NEXT-31729
author: Lars Kemper
author_email: l.kemper@shopwell.com
author_github: @LarsKemper
---
# Core
* Added `RuleConfig::DEFAULT_DIGITS` constant to `Shopwell\Core\Framework\Rule\RuleConfig.php`
* Changed `numberField()` method in `Shopwell\Core\Framework\Rule\RuleConfig.php` to increase the default number field digits.

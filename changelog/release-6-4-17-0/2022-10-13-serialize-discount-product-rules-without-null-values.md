---
title: Serialize discount product rules without null values
issue: NEXT-23652
author: d.neustadt
author_email: d.neustadt@shopwell.com
author_github: dneustadt
---
# Core
* Changed `Shopwell\Core\Framework\Struct\Struct\Rule::jsonSerialize` to filter out `null` values

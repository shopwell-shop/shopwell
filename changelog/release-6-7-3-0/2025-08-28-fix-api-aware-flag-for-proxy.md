---
title: Fix API aware flag for proxied requests
issue: #7156
---
# Core
* Changed `\Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware::isSourceAllowed()` to also consider parent classes of the current source. 

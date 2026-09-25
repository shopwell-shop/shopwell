---
title: handle nullable app id
issue: NEXT-34131
author: Florian Keller
author_email: f.keller@shopwell.com
---
# Core
* Changed the return value form `Shopwell\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodEntity::getAppId()` to null|string.

---
title: Fix error redirect on account login
issue: NEXT-26995
author: Max Stegmeyer
author_email: m.stegmeyer@shopwell.com
---

# Core
* Deprecated the constructor of the following exceptions, as there now is a domain exception in `Shopwell\Core\Framework\Routing\RoutingException`
  * `Shopwell\Core\Framework\Routing\Exception\MissingRequestParameterException`
  * `Shopwell\Core\Framework\Routing\Exception\InvalidRequestParameterException`

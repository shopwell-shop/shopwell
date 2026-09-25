---
title: Optimize float handling
issue: NEXT-11340
author: Oliver Skroblin
author_email: o.skroblin@shopwell.com 
author_github: Oliver Skroblin
---
# Core
* Added `\Shopwell\Core\Framework\Util\FloatComparator::cast` function to remove floating point problem
* Changed price objects inside the cart domain, they now use the `FloatComparator` to avoid the floating point problem

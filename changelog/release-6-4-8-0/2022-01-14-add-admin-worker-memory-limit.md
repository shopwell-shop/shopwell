---
title: Add memory limit to admin worker
issue: NEXT-19688
author: Niklas Büchner
author_email: niklas.buechner@pickware.de
---
# Core
* Added `\Shopwell\Core\Framework\Util\MemorySizeCalculator` to convert human readable memory sizes to the actual byte sizes.
___
# Administration
* Added a memory limit to the admin worker.

---
title: Ensure profiler is not loaded in production mode
issue: NEXT-28902
---
# Core
* Changed `\Shopwell\Core\HttpKernel` to not load the profiler when Shopwell is in production mode

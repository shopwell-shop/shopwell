---
title: Make affected SalesChannelIds available in ThemeAssignmentException
issue: NEXT-14688
---
# Storefront
* Added `stillAssignedSalesChannelIds` to `\Shopwell\Storefront\Theme\Exception\ThemeAssignmentException`.
* Changed `\Shopwell\Storefront\Theme\ThemeLifecycleHandler` to add the still assigned SalesChannelIds to the `ThemeAssignmentException`.

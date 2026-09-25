---
title: Fix tracking codes cannot be exported
issue: NEXT-25067
---
# Core
- Changed method `Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\OrderSerializer::serialize` to implode `orderDeliveries.trackingCodes` 

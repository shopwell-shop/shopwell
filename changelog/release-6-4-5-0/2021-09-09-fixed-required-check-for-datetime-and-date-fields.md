---
title: Fixed Required()-Flag check for DateTimeField and DateField
issue: NEXT-13680
---
# Core
*  Changed `\Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\DateTimeFieldSerializer` and `\Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\DateFieldSerializer` to not allow null values, when the field is marked as required.

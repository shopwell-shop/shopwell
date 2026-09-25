---
title: Fix composer check for replaces on plugin requirement validation
issue: NEXT-10746
---
# Core
*  Changed `Shopwell\Core\Framework\Plugin\Requirement\RequirementsValidator::checkRequirement()` to check `ConstraintInterface` instead of version string

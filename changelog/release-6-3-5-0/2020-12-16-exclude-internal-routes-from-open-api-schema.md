---
title: Exclude internal routes from open api schema 
issue: NEXT-12538
author: Stefan Sluiter
author_email: s.sluiter@shopwell.com 
author_github: @ssltg
---
# Core
* Changed `Shopwell\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiLoader` to exclude internal routes if it is not activated with a feature flag.

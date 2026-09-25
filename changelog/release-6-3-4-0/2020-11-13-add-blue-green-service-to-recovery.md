---
title: Added BlueGreenDeploymentService to the Recovery app
issue: NEXT-10525 
author: Patrick Stahl
author_email: p.stahl@shopwell.com
author_github: @PaddyS
---
# Core
* Added `Shopwell\Recovery\Install\Service\BlueGreenDeploymentService`, which sets the `BLUE_GREEN_DEPLOYMENT` env variable depending on the database user's permission of creating a trigger

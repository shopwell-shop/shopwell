---
title: Fix install lock creation on failure
author: Martin Bens
author_email: m.bens@shopwell.com
author_github: @SpiGAndromeda
---
# Core
* Changed `\Shopwell\Core\Maintenance\System\Command\SystemInstallCommand` to only create `install.lock` when installation succeeds
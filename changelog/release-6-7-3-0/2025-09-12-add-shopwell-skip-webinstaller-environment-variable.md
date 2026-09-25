---
title: Add env var for readonly filesystem support
issue: 12398
author: Martin Bens
author_email: m.bens@shopwell.com
author_github: @SpiGAndromeda
---
# Core
* Added `SHOPWELL_SKIP_WEBINSTALLER` environment variable to bypass installer for read-only filesystems
* Changed `public/index.php` to check environment variable before loading installer
* Changed `Shopwell\Core\Maintenance\System\Command\SystemInstallCommand` to skip install.lock and .htaccess file creation when environment variable is set
___
# Upgrade Information
## Environment Variable for PaaS Deployments
For deployments on platforms with read-only filesystems (such as Shopwell PaaS), you can now set the `SHOPWELL_SKIP_WEBINSTALLER` environment variable to bypass the web installer and install.lock file checks.

Any non-empty value will activate this feature:
```bash
SHOPWELL_SKIP_WEBINSTALLER=1
SHOPWELL_SKIP_WEBINSTALLER=true
SHOPWELL_SKIP_WEBINSTALLER=enabled
```

This allows Shopwell to run without requiring write access to create the `install.lock` file in the project root or the `.htaccess` file in the public directory.
---
title: Fix theme config directory creation for storefront watcher
issue: 13051
author: Björn Meyer
author_email: b.meyer@shopwell.com
author_github: @BrocksiNet
---
# Storefront
* Added directory existence check in `StaticFileConfigDumper::dumpConfig()` to ensure `theme-config` directory is created before writing theme configuration files


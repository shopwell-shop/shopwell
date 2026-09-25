---
title: Added configurable administration path name
issue: NEXT-15526
author: mynameisbogdan
author_email: mynameisbogdan@protonmail.com
author_github: mynameisbogdan
---
# Administration
* Added new env `SHOPWELL_ADMINISTRATION_PATH_NAME` and parameter `shopwell_administration.path_name` to configure the route `administration.index`
* Changed `Shopwell\Administration\Framework\Routing\AdministrationRouteScope` to set `$allowedPaths` property in constructor using parameter `shopwell_administration.path_name` 

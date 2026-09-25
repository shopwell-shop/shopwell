---
title:              Fix maintenance page
issue:              NEXT-9121
author:             Sebastian König
author_email:       s.koenig@tinect.de
author_github:      @tinect
---
# Storefront
*  Added auto reload to `Storefront/storefront/page/error/error-maintenance.html.twig` with new block `error_maintenance_script_reload`
*  Added new method `shouldRedirectToShop` to `Shopwell\Storefront\Framework\Routing\MaintenanceModeResolver`
*  Added service `Shopwell\Storefront\Framework\Routing\MaintenanceModeResolver` to `Shopwell\Storefront\Controller\MaintenanceController\MaintenanceController`
*  Added status code `HTTP_SERVICE_UNAVAILABLE` to result for active maintenance mode in `Shopwell\Storefront\Controller\MaintenanceController\MaintenanceController`
*  Added header `Retry-After` to result for active maintenance mode in `Shopwell\Storefront\Controller\MaintenanceController\MaintenanceController`
*  Added status code `HTTP_TEMPORARY_REDIRECT` to redirect result in method `maintenanceResolver` in `Shopwell\Storefront\Framework\Routing\StorefrontSubscriber`

---
title: Adding ACL for Routes
issue: NEXT-10714 
---
# Core
*  Added `Shopwell\Core\Framework\Api\Controller\AclController` to provide all core privileges
*  Added `Shopwell\Core\Framework\Routing\Annotation\Acl` to `Shopwell\Core\System\SystemConfig\Api\SystemConfigController`, `Shopwell\Core\Framework\Api\Controller\AclController`, `Shopwell\Core\Framework\Api\Controller\CacheController` and `Shopwell\Core\Framework\Api\Controller\UserController`
*  Added Event `Shopwell\Core\Framework\Api\Acl\Event\AclGetAdditionalPrivilegesEvent`
___
# API
*  Added ACL permission check to protected Routes. A user needs to have admin rights or needs the route privilege to call a protected route.
___
# Administration
*  Added `sw-users-permissions-detailed-additional-permissions` component
*  Added `acl.api.service.js` to get core privileges


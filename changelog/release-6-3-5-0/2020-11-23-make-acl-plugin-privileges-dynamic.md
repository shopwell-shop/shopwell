---
title: Make acl plugin privileges dynamic
issue: NEXT-11917
author: Lennart Tinkloh
author_email: l.tinkloh@shopwell.com 
author_github: lernhart
---
# Core
* Added `Shopwell\Core\Framework\Api\Acl\Role\AclRoleEvents` to add some acl role specific events.
* Added `Shopwell\Core\Framework\Plugin\Subscriber\PluginAclPrivilegesSubscriber` to subscribe to acl.loaded event and add plugin privileges on runtime.
* Added `Shopwell\Core\Framework\Plugin::enrichPrivileges()` method. 
* Deprecated `Shopwell\Core\Framework\Plugin::addPrivileges()` for tag:v6.4.0.0.
* Deprecated `Shopwell\Core\Framework\Plugin::removePrivileges()` for tag:v6.4.0.0.
___
# Upgrade Information

## Plugin acl - Use `enrichPrivileges` instead of `addPrivileges`
The current behaviour of adding privileges via plugins is deprecated for 6.4.0.0.
Instead of writing custom plugin privileges via `Shopwell\Core\Framework\Plugin::addPrivileges()` right into the database, 
plugins now should override the new `enrichPrivileges()` method to add privileges on runtime.
This method should return an array in the following structure:

```php
<?php declare(strict_types=1);

namespace MyPlugin;

use Shopwell\Core\Framework\Plugin;

class SwagTestPluginAcl extends Plugin
{
    public function enrichPrivileges(): array
    {
        return [
            'product.viewer' => [
                'my_custom_privilege:read',
                'my_custom_privilege:write',
                'my_other_custom_privilege:read',
                // ...
            ],
            'product.editor' => [
                // ...
            ],
        ];
    }
}
```

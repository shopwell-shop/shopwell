---
title: Implement custom entities for plugins
issue: NEXT-22734
author: Marcel Brode
author_email: m.brode@shopwell.com
author_github: Marcel Brode
---
# Core
* Added Custom Entity handling to `\Shopwell\Core\Framework\Plugin\PluginLifecycleService`:
  * `::installPlugin`
  * `::uninstallPlugin`
  * `::updatePlugin`
* Changed `custom entity` entity to also contain an `plugin_id` column

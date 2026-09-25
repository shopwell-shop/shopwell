---
title: Prevent deleting default cms page
issue: NEXT-22347
---
# Core
* Added `\Shopwell\Core\Content\Cms\CmsException::OVERALL_DEFAULT_SYSTEM_CONFIG_DELETION_CODE` to throw an exception when trying to delete the overall cms page default.
* Added `\Shopwell\Core\Content\Cms\CmsException::DELETION_OF_DEFAULT_CODE` to throw an exception when trying to delete a sales channel specific cms page default.  
* Changed `\Shopwell\Core\System\SystemConfig\SystemConfigService.php` in order to fire `\Shopwell\Core\System\SystemConfig\Event\BeforeSystemConfigChangedEvent` if a change would affect the default cms pages.
* Added `\Shopwell\Core\Content\Cms\Subscriber\CmsPageDefaultChangeSubscriber` which will validate all default cms page related changes.
  * This subscriber will validate all related changes in the `system_config` and also for all cms pages. 
  * once `\Shopwell\Core\Framework\DataAbstractionLayer\Event\BeforeDeleteEvent` is fired the subscriber will throw `\Shopwell\Core\Content\Cms\CmsException::DELETION_OF_DEFAULT_CODE` when trying to delete a cms page which is defined as a default.
  * once `\Shopwell\Core\System\SystemConfig\Event\BeforeSystemConfigChangedEvent` is fired the subscriber will throw 
    * `\Shopwell\Core\Content\Cms\CmsException::OVERALL_DEFAULT_SYSTEM_CONFIG_DELETION_CODE` when trying to delete the overall default cms page (which is not tied to a specific sales channel).
    * `\Shopwell\Core\Content\Cms\Exception\PageNotFoundException` when trying to set an invalid cms page id as a default.

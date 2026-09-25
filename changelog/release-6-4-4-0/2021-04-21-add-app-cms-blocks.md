---
title: Allow app developers to extend the CMS by adding custom blocks  
issue: NEXT-14408
---
# Core
* Added new entity `app_cms_block` in `Shopwell\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockEntity`
* Added new tables
    * `app_cms_block`
    * `app_cms_block_translation`
* Added new classes
    * `Shopwell\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockCollection`
    * `Shopwell\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockDefinition`
    * `Shopwell\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockEntity`
    * `Shopwell\Core\Framework\App\Aggregate\CmsBlockTranslation\AppCmsBlockTranslationCollection`
    * `Shopwell\Core\Framework\App\Aggregate\CmsBlockTranslation\AppCmsBlockTranslationDefinition`
    * `Shopwell\Core\Framework\App\Aggregate\CmsBlockTranslation\AppCmsBlockTranslationEntity`
    * `Shopwell\Core\Framework\App\Api\AppCmsController`
    * `Shopwell\Core\Framework\App\Cms\Xml\Block`
    * `Shopwell\Core\Framework\App\Cms\Xml\Blocks`
    * `Shopwell\Core\Framework\App\Cms\Xml\Config`
    * `Shopwell\Core\Framework\App\Cms\Xml\DefaultConfig`
    * `Shopwell\Core\Framework\App\Cms\Xml\Slot`
    * `Shopwell\Core\Framework\App\Cms\AbstractBlockTemplateLoader`
    * `Shopwell\Core\Framework\App\Cms\BlockTemplateLoader`
    * `Shopwell\Core\Framework\App\Cms\CmsExtensions`
    * `Shopwell\Core\Framework\App\Exception\AppCmsExtensionException`
    * `Shopwell\Core\Framework\App\Lifecycle\Persister\CmsBlockPersister`
* Added abstract method `Shopwell\Core\Framework\App\Lifecycle\AbstractAppLoader::getCmsExtensions`
* Added method `Shopwell\Core\Framework\App\Lifecycle\AppLoader::getCmsExtensions`
* Updated private method to `Shopwell\Core\Framework\App\Lifecycle\AppLifeCycle::updateApp` to persist CMS blocks provided by app
* Added new XML schema definition `cms-1.0.xsd`
* Updated `Shopwell\Core\Framework\App\AppDefinition::defineFields` with new one-to-many association towards `AppCmsBlockDefinition` 
* Added new property `Shopwell\Core\Framework\App\AppEntity::$cmsBlocks`
___
# API
* Added route `/api/app-system/cms/blocks` to retrieve custom CMS blocks provided by **activated** apps
___
# Upgrade Information
Existing apps **DO NOT** break with the introduced changes as they are backwards compatible.

If you implement `Shopwell\Core\Framework\App\Lifecycle\AbstractAppLoader` make sure to add the new method `::getCmsExtensions`.

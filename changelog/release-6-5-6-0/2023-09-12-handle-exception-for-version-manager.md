---
title: Handle exception for VersionManager
issue: NEXT-30181
---
# Core
* Added 2 new exception methods `cannotCreateNewVersion` and `versionMergeAlreadyLocked` in `Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException`
* Added an alternative exception by throwing `DataAbstractionLayerException::cannotCreateNewVersion()` in `cloneEntity` method of `Shopwell\Core\Framework\DataAbstractionLayer\VersionManager`
* Added an alternative exception by throwing `DataAbstractionLayerException::versionMergeAlreadyLocked()` in `merge` method of `Shopwell\Core\Framework\DataAbstractionLayer\VersionManager`

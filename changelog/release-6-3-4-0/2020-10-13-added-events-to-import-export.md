---
title: Added events in import/export
issue: NEXT-11387
author: Bjoern Herzke
author_github: @wrongspot
---
# Core
* Added new `\Shopwell\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent` to observe a record before it is stored in database
* Added new `\Shopwell\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent` to observe a record after it is stored in database
* Added new `\Shopwell\Core\Content\ImportExport\Event\ImportExportExceptionImportRecordEvent` to observe a record while an exception occurred
* Added new `\Shopwell\Core\Content\ImportExport\Event\ImportExportBeforeExportRecordEvent` to observe a record before it is written in export file

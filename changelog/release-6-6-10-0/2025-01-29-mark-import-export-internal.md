---
title: Mark parts of the Import/Export functionality as internal
issue: NEXT-40446
---
# Core
* Added `\Shopwell\Core\Content\ImportExport\Event\ImportExportAfterProcessFinishedEvent` which is dispatched upon completion of import/export operations. This event exposes the `Context`, `ImportExportLogEntity`, and progress information through its respective getter methods.
* Deprecated the following classes which will marked as internal in 6.7.0.0
  * `\Shopwell\Core\Content\ImportExport\ImportExport`
  * `\Shopwell\Core\Content\ImportExport\Processing\Pipe\AbstractPipe`
  * `\Shopwell\Core\Content\ImportExport\Processing\Pipe\AbstractPipeFactory`
  * `\Shopwell\Core\Content\ImportExport\Processing\Pipe\ChainPipe.php`
  * `\Shopwell\Core\Content\ImportExport\Processing\Pipe\EntityPipe.php`
  * `\Shopwell\Core\Content\ImportExport\Processing\Pipe\KeyMappingPipe.php`
  * `\Shopwell\Core\Content\ImportExport\Processing\Pipe\PipeFactory.php`
___
# Next Major Version Changes
## Changes to the import/export functionality
The following classes are now marked as internal:
* `\Shopwell\Core\Content\ImportExport\ImportExport`
* `\Shopwell\Core\Content\ImportExport\Processing\Pipe\AbstractPipe`
* `\Shopwell\Core\Content\ImportExport\Processing\Pipe\AbstractPipeFactory`
* `\Shopwell\Core\Content\ImportExport\Processing\Pipe\ChainPipe`
* `\Shopwell\Core\Content\ImportExport\Processing\Pipe\EntityPipe`
* `\Shopwell\Core\Content\ImportExport\Processing\Pipe\KeyMappingPipe`
* `\Shopwell\Core\Content\ImportExport\Processing\Pipe\PipeFactory`

This method is removed without replacement `\Shopwell\Core\Content\ImportExport\Processing\Pipe\AbstractPipe::getDecorated()` cause the `AbstractPipe` class is now marked as internal.

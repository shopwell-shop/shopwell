---
title: Added option to abort imports or exports in progress
issue: NEXT-19152
author: d.neustadt
author_email: d.neustadt@shopwell.com 
author_github: dneustadt
---
# Core
* Changed `Shopwell\Core\Content\ImportExport\Message\ImportExportHandler` to not handle import/export if state is aborted
___
# Administration
* Added option in context menu of import/export activities to abort processes in progress

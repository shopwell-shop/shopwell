---
title: Fix download file name and fallback cannot contains slashes
issue: NEXT-30240
---
# Core
* Added a new domain exception `\Shopwell\Core\Content\ImportExport\ImportExportException`
* Changed `\Shopwell\Core\Content\ImportExport\Service\DownloadService::createFileResponse` to strip slashes before return the stream response
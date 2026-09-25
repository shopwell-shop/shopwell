---
title: Fix thumbnail generation when physical file is missing and refactor GenerateThumbnailsHandler to distinguish between message types
issue: NEXT-7754
author: David Fecke
author_github: @leptoquark1
---
# Core
* Changed `updateThumbnails` function in `Shopwell\Core\Content\Media\Thumbnail\ThumbnailService` so that the physical presence of the thumbnail file is respected
* Added new bool parameter `$strict` to method `Shopwell\Core\Content\Media\Thumbnail\ThumbnailService::updateThumbnails`
* Added option `--strict` to command `media:generate-thumbnails`. If supplied, a physical file check for existing thumbnails is performed.
* Changed `media:generate-thumbnails` to behave in the same way, regardless whether it's executed with or without the `--async` flag.
* Changed `Shopwell\Core\Content\Media\Message\GenerateThumbnailsHandler` to distinguish which method is executed in the`Shopwell\Core\Content\Media\Thumbnail\ThumbnailService` based on the message type.
* Added two methods `::isStrict()` and `::setIsStrict()` and its corresponding property `$isStrict` to class `Shopwell\Core\Content\Media\Message\UpdateThumbnailsMessage`
* Changed `Shopwell\Core\Content\Media\Message\GenerateThumbnailsHandler` to call `Shopwell\Core\Content\Media\Thumbnail\ThumbnailService::generate` if a `UpdateThumbnailsMessage` is handled, `Shopwell\Core\Content\Media\Thumbnail\ThumbnailService::upadteThumbnails` otherwise
* Changed `Shopwell\Core\Content\Media\Message\GenerateThumbnailsHandler::handle()` to pass `$isStrict` to `Shopwell\Core\Content\Media\Thumbnail\ThumbnailService::updateThumbnails()`
* Changed `Shopwell\Core\Content\Media\Commands\GenerateThumbnailsCommand::generateThumbnails()` to pass `$isStrict` to `Shopwell\Core\Content\Media\Thumbnail\ThumbnailService::updateThumbnails()`

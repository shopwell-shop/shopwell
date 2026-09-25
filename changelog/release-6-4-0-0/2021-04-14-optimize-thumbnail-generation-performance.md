---
title:              Optimize thumbnail generation performance
issue:              NEXT-14411
author:             OliverSkroblin
author_email:       o.skroblin@shopwell.com
author_github:      @OliverSkroblin
---
# Core
* Added `\Shopwell\Core\Content\Media\Thumbnail\ThumbnailService::generate` to generate thumbnails for multiple entities at once
* Deprecated `\Shopwell\Core\Content\Media\Thumbnail\ThumbnailService::generateThumbnails`, use `\Shopwell\Core\Content\Media\Thumbnail\ThumbnailService::generate` instead

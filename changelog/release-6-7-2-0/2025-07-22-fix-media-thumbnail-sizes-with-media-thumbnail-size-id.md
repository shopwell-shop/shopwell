---
title: Fix media thumbnail sizes with mediaThumbnailSizeId
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: gecolay
---
# Core
* Added `mediaThumbnailSizeId` field to `Shopwell\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition`
* Changed `Shopwell\Core\Content\Media\Thumbnail\ThumbnailService` to correctly check thumbnail existing by size id & insert correct media with and height into database

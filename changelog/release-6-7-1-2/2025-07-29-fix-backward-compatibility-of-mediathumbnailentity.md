---
title: Fix backward compatibility of MediaThumbnailEntity
issue: 10040
author: Dominik Grothaus
author_email: d.grothaus@shopwell.com
---
# Core
* Changed `$mediaId` of `Shopwell\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity` from `string` to `?string` to have the same behaviour like in Shopwell 6.6 so that objects serialized in Shopwell 6.6 can be unserialized in 6.7 and don't throw an exception.

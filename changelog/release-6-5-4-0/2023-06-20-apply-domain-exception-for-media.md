---
title: Apply domain exception for media
issue: NEXT-26928
---
# Core
* Added new domain exception class `Shopwell\Core\Content\Media\MediaException`.
* Deprecated the following exceptions in replacement for Domain Exceptions:
  * `Shopwell\Core\Content\Media\Exception\CouldNotRenameFileException`
  * `Shopwell\Core\Content\Media\Exception\DisabledUrlUploadFeatureException`
  * `Shopwell\Core\Content\Media\Exception\EmptyMediaFilenameException`
  * `Shopwell\Core\Content\Media\Exception\EmptyMediaIdException`
  * `Shopwell\Core\Content\Media\Exception\FileExtensionNotSupportedException`
  * `Shopwell\Core\Content\Media\Exception\IllegalFileNameException`
  * `Shopwell\Core\Content\Media\Exception\IllegalUrlException`
  * `Shopwell\Core\Content\Media\Exception\MediaFolderNotFoundException`
  * `Shopwell\Core\Content\Media\Exception\MissingFileExtensionException`
  * `Shopwell\Core\Content\Media\Exception\StrategyNotFoundException`
  * `Shopwell\Core\Content\Media\Exception\StreamNotReadableException`
  * `Shopwell\Core\Content\Media\Exception\ThumbnailCouldNotBeSavedException`
  * `Shopwell\Core\Content\Media\Exception\ThumbnailNotSupportedException`
  * `Shopwell\Core\Content\Media\Exception\UploadException`

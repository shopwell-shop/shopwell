---
title: Move max upload filesize logic
issue: NEXT-32087
author_github: @Dominik28111
---
# Core
* Added method `Shopwell\Core\Framework\Util\MemorySizeCalculator::getMaxUploadSize()` to calculate the maximum upload size.
* Changed method `Shopwell\Core\Content\ImportExport\Service\SupportedFeaturesService::getUploadFileSizeLimit()` to use the new method `Shopwell\Core\Framework\Util\MemorySizeCalculator::getMaxUploadSize()`.

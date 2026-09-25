---
title: 2022-10-18-fix-webp-animation-bit-flag-recognition
issue: NEXT-9366
author: dbeyer
author_email: d.beyer@shopwell.com
author_github: N0Manches
---
# Core
* Added `fseek` here `\Shopwell\Core\Content\Media\TypeDetector\ImageTypeDetector::isWebpAnimated` to jump with the file pointer to the webp extension flags byte
* Changed the webp test file to a file with the 'Extended File Format'

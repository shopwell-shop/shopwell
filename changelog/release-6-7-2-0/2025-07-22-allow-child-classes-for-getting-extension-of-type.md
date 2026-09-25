---
title: Allow child classes for getting extension of type
author: Michel Bade
author_email: m.bade@shopwell.com
author_github: @cyl3x
---
# Core
* Changed `Shopwell\Core\Framework\Struct\ExtendableTrait::hasExtensionOfType` to use `instanceof` to allow `getExtensionOfType` to return subclasses of that extension.

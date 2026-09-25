---
title: Add possibility for default cms media
issue: NEXT-20073
---
# Core
* Added the possibility to resolve default media via the filepath.
* Added `Shopwell/Core/Content/Media/Cms/AbstractDefaultMediaResolver.php` which gets implemented in `Shopwell/Core/Content/Media/Cms/DefaultMediaResolver.php`.
* Added `Shopwell/Core/Content/Media/Cms/DefaultMediaResolver.php` which creates a `MediaEntity` from a given filepath relative to the `public/bundles/` directory.
* Changed `Shopwell/Core/Content/Media/Cms/Type/ImageSliderTypeDataResolver.php` to be able to load default images in the storefront.
* Changed `Shopwell/Core/Content/Media/Cms/ImageCmsElementResolver.php` to be able to load default images in the storefront.
* Changed `Shopwell/Core/Content/Cms/DataAbstractionLayer/FieldSerializer/SlotConfigFieldSerializer.php` to allow `default` as a valid choice for fields.
___
# Storefront
* Added `Shopwell/Storefront/Page/Cms/DefaultMediaResolver.php`
  * decorates `Shopwell/Core/Content/Media/Cms/AbstractDefaultMediaResolver.php`
  * adds the `url` and `translations` to the `MediaEntity`
  * makes it possible to display default Cms media in the Storefront from a given filepath relative to the `public/bundles/{pathToMyFile}`
  * an example would look like this: 
    * file to show in the storefront: `my_preview_image.png`
    * file structure: `public/bundles/myBundleName/assets/default/cms/my_preview_image.png`
    * format to access the image in the storefront: `myBundleName/assets/default/cms/my_preview_image.png`

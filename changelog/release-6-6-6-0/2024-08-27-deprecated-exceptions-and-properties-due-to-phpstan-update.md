---
title: Deprecated exceptions and properties due to PHPStan update
issue: NEXT-37561
author: Michael Telgmann
author_github: @mitelg
---
# Core
* Deprecated properties of `\Shopwell\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeEntity`. They will be typed natively in v6.7.0.0.
* Deprecated properties of `\Shopwell\Core\Framework\DataAbstractionLayer\Entity`. They will be typed natively in v6.7.0.0.
* Deprecated properties of `\Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField`. They will be typed natively in v6.7.0.0.
* Deprecated properties of `\Shopwell\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField`. They will be typed natively in v6.7.0.0.
* Deprecated exception `\Shopwell\Core\Framework\Api\Exception\UnsupportedEncoderInputException`. It will be removed in v6.7.0.0. Use `\Shopwell\Core\Framework\Api\ApiException::unsupportedEncoderInput` instead.
* Deprecated exception `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\CanNotFindParentStorageFieldException`. It will be removed in v6.7.0.0. Use `\Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException::cannotFindParentStorageField` instead.
* Deprecated exception `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException`. It will be removed in v6.7.0.0. Use `\Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException::internalFieldAccessNotAllowed` instead.
* Deprecated exception `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\InvalidParentAssociationException`. It will be removed in v6.7.0.0. Use `\Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException::invalidParentAssociation` instead.
* Deprecated exception `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\ParentFieldNotFoundException`. It will be removed in v6.7.0.0. Use `\Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException::parentFieldNotFound` instead.
* Deprecated exception `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\PrimaryKeyNotProvidedException`. It will be removed in v6.7.0.0. Use `\Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException::primaryKeyNotProvided` instead.
* Deprecated method `\Shopwell\Core\Framework\DataAbstractionLayer\Entity::__get`. It will throw a `\Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException` instead of a `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException` in v6.7.0.0.
* Deprecated method `\Shopwell\Core\Framework\DataAbstractionLayer\Entity::get`. It will throw a `\Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException` instead of a `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException` in v6.7.0.0.
* Deprecated method `\Shopwell\Core\Framework\DataAbstractionLayer\Entity::checkIfPropertyAccessIsAllowed`. It will throw a `\Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException` instead of a `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException` in v6.7.0.0.
* Deprecated method `\Shopwell\Core\Framework\DataAbstractionLayer\Entity::get`. It will throw a `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\PropertyNotFoundException` instead of a `\InvalidArgumentException` in v6.7.0.0.
___
# Upgrade Information
## Native typehints of properties
The properties of the following classes will be typed natively in v6.7.0.0.
If you have extended from those classes and overwritten the properties, you can already set the correct type.
* `\Shopwell\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeEntity`
* `\Shopwell\Core\Framework\DataAbstractionLayer\Entity`
* `\Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField`
* `\Shopwell\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField`
## Deprecated exceptions
The following exceptions were deprecated and will be removed in v6.7.0.0.
You can already catch the replacement exceptions additionally to the deprecated ones.
* `\Shopwell\Core\Framework\Api\Exception\UnsupportedEncoderInputException`. Also catch `\Shopwell\Core\Framework\Api\ApiException`.
* `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\CanNotFindParentStorageFieldException`. Also catch `\Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException`.
* `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException`. Also catch `\Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException`.
* `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\InvalidParentAssociationException`. Also catch `\Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException`.
* `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\ParentFieldNotFoundException`. Also catch `\Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException`.
* `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\PrimaryKeyNotProvidedException`. Also catch `\Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException`.
## Deprecated methods
The following methods of the `\Shopwell\Core\Framework\DataAbstractionLayer\Entity` class were deprecated and will throw different exceptions in v6.7.0.0.
You can already catch the replacement exceptions additionally to the deprecated ones.
* `\Shopwell\Core\Framework\DataAbstractionLayer\Entity::__get`. Also catch `\Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException` in addition to `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException`.
* `\Shopwell\Core\Framework\DataAbstractionLayer\Entity::get`. Also catch `\Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException` in addition to `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException`.
* `\Shopwell\Core\Framework\DataAbstractionLayer\Entity::checkIfPropertyAccessIsAllowed`. Also catch `\Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException` in addition to `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException`.
* `\Shopwell\Core\Framework\DataAbstractionLayer\Entity::get`. Also catch `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\PropertyNotFoundException` in addition to `\InvalidArgumentException`.
___
# Next Major Version Changes
## Removal of deprecated exceptions
The following exceptions were removed:
* `\Shopwell\Core\Framework\Api\Exception\UnsupportedEncoderInputException`
* `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\CanNotFindParentStorageFieldException`
* `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException`
* `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\InvalidParentAssociationException`
* `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\ParentFieldNotFoundException`
* `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\PrimaryKeyNotProvidedException`
## Entity class throws different exceptions
The following methods of the `\Shopwell\Core\Framework\DataAbstractionLayer\Entity` class are now throwing different exceptions:
* `\Shopwell\Core\Framework\DataAbstractionLayer\Entity::__get` now throws a `\Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException` instead of a `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException`.
* `\Shopwell\Core\Framework\DataAbstractionLayer\Entity::get` now throws a `\Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException` instead of a `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException`.
* `\Shopwell\Core\Framework\DataAbstractionLayer\Entity::checkIfPropertyAccessIsAllowed` now throws a `\Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException` instead of a `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException`.
* `\Shopwell\Core\Framework\DataAbstractionLayer\Entity::get` now throws a `\Shopwell\Core\Framework\DataAbstractionLayer\Exception\PropertyNotFoundException` instead of a `\InvalidArgumentException`.

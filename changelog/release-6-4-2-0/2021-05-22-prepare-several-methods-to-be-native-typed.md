---
title: Prepare several methods to be native typed
issue: NEXT-14973
---
# Core
* Deprecated the following methods. They will all have a native typehint for their parameters and/or return type with Shopwell 6.5.0.0
  * Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer::iterate
  * Shopwell\Core\Checkout\Customer\DataAbstractionLayer\CustomerIndexer::iterate
  * Shopwell\Core\Checkout\Promotion\DataAbstractionLayer\PromotionIndexer::iterate
  * Shopwell\Core\Content\Category\DataAbstractionLayer\CategoryIndexer::iterate
  * Shopwell\Core\Content\LandingPage\DataAbstractionLayer\LandingPageIndexer::iterate
  * Shopwell\Core\Content\Media\DataAbstractionLayer\MediaFolderConfigurationIndexer::iterate
  * Shopwell\Core\Content\Media\DataAbstractionLayer\MediaFolderIndexer::iterate
  * Shopwell\Core\Content\Media\DataAbstractionLayer\MediaIndexer::iterate
  * Shopwell\Core\Content\Product\DataAbstractionLayer\ProductIndexer::iterate
  * Shopwell\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater::iterate
  * Shopwell\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexer::iterate
  * Shopwell\Core\Content\Rule\DataAbstractionLayer\RuleIndexer::iterate
  * Shopwell\Core\System\SalesChannel\DataAbstractionLayer\SalesChannelIndexer::iterate

  * Shopwell\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent::addData
  * Shopwell\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent::addTemplateData

  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\BlobFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\BoolFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\CalculatedPriceFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\CartPriceFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\CashRoundingConfigFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\ConfigJsonFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\CustomFieldsSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\DateFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\DateTimeFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\EmailFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\FkFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\FloatFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\IdFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\IntFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\ListFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\LongTextFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToManyAssociationFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToOneAssociationFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\OneToManyAssociationFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\OneToOneAssociationFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\PasswordFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\PHPUnserializeFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceDefinitionFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\ReferenceVersionFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\RemoteAddressFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\StringFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\TranslatedFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\TranslationsAssociationFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\VersionDataPayloadFieldSerializer::decode
  * Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\VersionFieldSerializer::decode

  * Shopwell\Core\Kernel::registerBundles
  * Shopwell\Core\Kernel::getProjectDir

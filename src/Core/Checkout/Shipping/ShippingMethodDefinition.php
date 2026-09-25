<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Shipping;

use Shopwell\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopwell\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceDefinition;
use Shopwell\Core\Checkout\Shipping\Aggregate\ShippingMethodTag\ShippingMethodTagDefinition;
use Shopwell\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\ShippingMethodTranslationDefinition;
use Shopwell\Core\Content\Media\MediaDefinition;
use Shopwell\Core\Content\Rule\RuleDefinition;
use Shopwell\Core\Framework\App\Aggregate\AppShippingMethod\AppShippingMethodDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\DeliveryTime\DeliveryTimeDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelShippingMethod\SalesChannelShippingMethodDefinition;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;
use Shopwell\Core\System\Tag\TagDefinition;
use Shopwell\Core\System\Tax\TaxDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class ShippingMethodDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'shipping_method';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ShippingMethodCollection::class;
    }

    public function getEntityClass(): string
    {
        return ShippingMethodEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'taxType' => ShippingMethodEntity::TAX_TYPE_AUTO,
            'position' => ShippingMethodEntity::POSITION_DEFAULT,
            'active' => ShippingMethodEntity::ACTIVE_DEFAULT,
        ];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of shipping method.'),
            (new TranslatedField('name'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new StringField('technical_name', 'technicalName'))->addFlags(new ApiAware(), new Required()),
            (new BoolField('active', 'active'))->addFlags(new ApiAware())->setDescription('When boolean value is `true`, the shipping methods are available for selection in the storefront.'),
            (new IntField('position', 'position'))->addFlags(new ApiAware())->setDescription('The order of the tabs of your defined shipping methods in the storefront by entering numerical values like 1,2,3, etc.'),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            (new FkField('availability_rule_id', 'availabilityRuleId', RuleDefinition::class))->setDescription('Unique identity of availability rule.'),
            (new FkField('media_id', 'mediaId', MediaDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of media.'),
            (new FkField('delivery_time_id', 'deliveryTimeId', DeliveryTimeDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of deliveryTime.'),
            (new StringField('tax_type', 'taxType', 50))->addFlags(new ApiAware(), new Required())->setDescription('Refers `Free`, `Net` or `Gross` type of taxes.'),
            (new FkField('tax_id', 'taxId', TaxDefinition::class))->setDescription('Unique identity of tax.'),
            (new ManyToOneAssociationField('deliveryTime', 'delivery_time_id', DeliveryTimeDefinition::class, 'id'))->addFlags(new ApiAware())->setDescription('Estimated delivery time information'),
            (new TranslatedField('description'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::LOW_SEARCH_RANKING)),
            (new TranslatedField('trackingUrl'))->addFlags(new ApiAware()),
            (new TranslationsAssociationField(ShippingMethodTranslationDefinition::class, 'shipping_method_id'))->addFlags(new ApiAware(), new Required()),
            (new ManyToOneAssociationField('availabilityRule', 'availability_rule_id', RuleDefinition::class))->addFlags(new ApiAware())->setDescription('Rule defining when this shipping method is available'),
            (new OneToManyAssociationField('prices', ShippingMethodPriceDefinition::class, 'shipping_method_id', 'id'))->addFlags(new ApiAware(), new CascadeDelete())->setDescription('Shipping prices based on weight, volume, or cart value'),
            (new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class))->addFlags(new ApiAware())->setDescription('Shipping method logo or carrier image'),
            (new ManyToManyAssociationField('tags', TagDefinition::class, ShippingMethodTagDefinition::class, 'shipping_method_id', 'tag_id'))->addFlags(new ApiAware())->setDescription('Tags for organizing shipping methods'),

            // Reverse Association, not available in sales-channel-api
            (new OneToManyAssociationField('orderDeliveries', OrderDeliveryDefinition::class, 'shipping_method_id', 'id'))->addFlags(new RestrictDelete()),
            new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, SalesChannelShippingMethodDefinition::class, 'shipping_method_id', 'sales_channel_id'),
            (new OneToManyAssociationField('salesChannelDefaultAssignments', SalesChannelDefinition::class, 'shipping_method_id', 'id'))->addFlags(new RestrictDelete()),
            (new ManyToOneAssociationField('tax', 'tax_id', TaxDefinition::class))->addFlags(new ApiAware())->setDescription('Tax configuration for shipping costs'),
            (new OneToOneAssociationField('appShippingMethod', 'id', 'shipping_method_id', AppShippingMethodDefinition::class, false))->addFlags(new CascadeDelete()),
        ]);
    }
}

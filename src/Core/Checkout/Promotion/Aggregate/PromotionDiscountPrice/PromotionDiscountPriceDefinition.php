<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice;

use Shopwell\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class PromotionDiscountPriceDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'promotion_discount_prices';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return PromotionDiscountPriceEntity::class;
    }

    public function getCollectionClass(): string
    {
        return PromotionDiscountPriceCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return PromotionDiscountDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of promotion discount price.'),
            (new FkField('discount_id', 'discountId', PromotionDiscountDefinition::class))->addFlags(new Required())->setDescription('Unique identity of discount.'),
            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->addFlags(new Required())->setDescription('Unique identity of currency.'),
            (new FloatField('price', 'price'))->addFlags(new Required())->setDescription('Price of the discount.'),
            new ManyToOneAssociationField('promotionDiscount', 'discount_id', PromotionDiscountDefinition::class, 'id', false),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, 'id', false),
        ]);
    }
}

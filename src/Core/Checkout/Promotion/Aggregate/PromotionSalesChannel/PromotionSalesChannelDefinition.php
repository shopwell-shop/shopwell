<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Promotion\Aggregate\PromotionSalesChannel;

use Shopwell\Core\Checkout\Promotion\PromotionDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class PromotionSalesChannelDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'promotion_sales_channel';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return PromotionSalesChannelEntity::class;
    }

    public function getCollectionClass(): string
    {
        return PromotionSalesChannelCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return PromotionDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of promotion on sales channel.'),
            (new FkField('promotion_id', 'promotionId', PromotionDefinition::class))->addFlags(new Required())->setDescription('Unique identity of promotion.'),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new Required())->setDescription('Unique identity of sales channel.'),
            (new IntField('priority', 'priority'))->addFlags(new Required())->setDescription('A numerical value to prioritize one of the promotion saleschannels from the list.'),
            new ManyToOneAssociationField('promotion', 'promotion_id', PromotionDefinition::class, 'id', false),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
        ]);
    }
}

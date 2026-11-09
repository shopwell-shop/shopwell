<?php declare(strict_types=1);

namespace Shopwell\Storefront\ContentSystem\HeaderContentLayout;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * Header content layout assignment with domain-aware resolution.
 *
 * Resolution priority: Domain+SalesChannel → SalesChannel → Global (null).
 * If domain_id is set, sales_channel_id MUST also be set.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class HeaderContentLayoutDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'header_content_layout';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return HeaderContentLayoutEntity::class;
    }

    public function getCollectionClass(): string
    {
        return HeaderContentLayoutCollection::class;
    }

    public function since(): string
    {
        return '6.7.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(AdminApiSource::class), new PrimaryKey(), new Required()),

            (new FkField('domain_id', 'domainId', SalesChannelDomainDefinition::class))->addFlags(new ApiAware(AdminApiSource::class)),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new ApiAware(AdminApiSource::class)),
            (new FkField('content_layout_id', 'contentLayoutId', ContentLayoutDefinition::class))->addFlags(new ApiAware(AdminApiSource::class), new Required()),

            new ManyToOneAssociationField('domain', 'domain_id', SalesChannelDomainDefinition::class, 'id', false),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
            new ManyToOneAssociationField('contentLayout', 'content_layout_id', ContentLayoutDefinition::class, 'id', false),
        ]);
    }
}

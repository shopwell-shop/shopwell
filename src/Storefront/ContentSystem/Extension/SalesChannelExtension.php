<?php declare(strict_types=1);

namespace Shopwell\Storefront\ContentSystem\Extension;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;
use Shopwell\Storefront\ContentSystem\FooterContentLayout\FooterContentLayoutDefinition;
use Shopwell\Storefront\ContentSystem\HeaderContentLayout\HeaderContentLayoutDefinition;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToManyAssociationField('headerContentLayouts', HeaderContentLayoutDefinition::class, 'sales_channel_id', 'id')
        );
        $collection->add(
            new OneToManyAssociationField('footerContentLayouts', FooterContentLayoutDefinition::class, 'sales_channel_id', 'id')
        );
    }

    public function getEntityName(): string
    {
        return SalesChannelDefinition::ENTITY_NAME;
    }
}

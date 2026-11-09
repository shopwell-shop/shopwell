<?php declare(strict_types=1);

namespace Shopwell\Storefront\ContentSystem\Extension;

use Shopwell\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\ContentSystem\FooterContentLayout\FooterContentLayoutDefinition;
use Shopwell\Storefront\ContentSystem\HeaderContentLayout\HeaderContentLayoutDefinition;

/**
 * @internal
 */
#[Package('framework')]
class ContentLayoutExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField('headerContentLayouts', HeaderContentLayoutDefinition::class, 'content_layout_id', 'id'))->addFlags(new RestrictDelete())
        );
        $collection->add(
            (new OneToManyAssociationField('footerContentLayouts', FooterContentLayoutDefinition::class, 'content_layout_id', 'id'))->addFlags(new RestrictDelete())
        );
    }

    public function getEntityName(): string
    {
        return ContentLayoutDefinition::ENTITY_NAME;
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout;

use Shopwell\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Content\Blog\BlogContentLayoutTest
 */
#[Package('discovery')]
class BlogContentLayoutExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add((new OneToManyAssociationField('blogContentLayouts', BlogContentLayoutDefinition::class, 'content_layout_id', 'id'))->addFlags(new Extension(), new RestrictDelete()));
    }

    public function getEntityName(): string
    {
        return ContentLayoutDefinition::ENTITY_NAME;
    }
}

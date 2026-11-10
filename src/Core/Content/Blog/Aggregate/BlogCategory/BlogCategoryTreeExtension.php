<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Aggregate\BlogCategory;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Content\Blog\BlogCategoryTreeTest
 */
#[Package('discovery')]
class BlogCategoryTreeExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add((new ParentFkField(BlogCategoryEntity::ENTITY_NAME))->addFlags(new ApiAware()));
        $collection->add((new ParentAssociationField(BlogCategoryEntity::ENTITY_NAME))->addFlags(new ApiAware()));
        $collection->add((new ChildrenAssociationField(BlogCategoryEntity::ENTITY_NAME))->removeFlag(CascadeDelete::class)->addFlags(new ApiAware(), new RestrictDelete()));
    }

    public function modifyFields(FieldCollection $collection): void
    {
        // The Blog entity owns this scalar property; keep its native FK at the entity root.
        $collection->get('parentId')?->removeFlag(Extension::class);
    }

    public function getEntityName(): string
    {
        return BlogCategoryEntity::ENTITY_NAME;
    }
}

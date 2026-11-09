<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Layout\Entity;

use Shopwell\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryContentLayoutDefinition;
use Shopwell\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageContentLayoutDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutDefinition;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\ContentSystem\Layout\Field\StoredElementListField;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Immutable;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentLayoutDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'content_layout';

    final public const LAYOUT_FIELD = 'layout';

    final public const ROOT_SOURCE_FIELD = 'root_source';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ContentLayoutEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ContentLayoutCollection::class;
    }

    public function since(): string
    {
        return '6.7.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(AdminApiSource::class), new PrimaryKey(), new Required()),
            (new StringField('name', 'name', 255))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new StringField('version', 'version', 20))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new StoredElementListField(self::LAYOUT_FIELD, self::LAYOUT_FIELD))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new StringField(self::ROOT_SOURCE_FIELD, 'rootSource'))->addFlags(new ApiAware(AdminApiSource::class), new Required(), new Immutable()),

            (new OneToManyAssociationField('productContentLayouts', ProductContentLayoutDefinition::class, 'content_layout_id', 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('categoryContentLayouts', CategoryContentLayoutDefinition::class, 'content_layout_id', 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('landingPageContentLayouts', LandingPageContentLayoutDefinition::class, 'content_layout_id', 'id'))->addFlags(new RestrictDelete()),
        ]);
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Category\Aggregate\CategoryContentLayout;

use Shopwell\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopwell\Core\Content\Product\ContentSystem\DataLoader\ProductListingDataLoader;
use Shopwell\Core\Content\Product\ContentSystem\DataLoader\ProductListingLoaderConfig;
use Shopwell\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class CategoryContentLayoutDefinition extends AbstractContentLayoutAssignableDefinition
{
    final public const ENTITY_NAME = 'category_content_layout';

    final public const CONTENT_LAYOUT_ENTITY_TYPE = 'category';

    final public const CONFIG_KEY_DEFAULT_CONTENT_LAYOUT = 'core.content_system.default_category_content_layout';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CategoryContentLayoutEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CategoryContentLayoutCollection::class;
    }

    public function getContentLayoutEntityType(): string
    {
        return self::CONTENT_LAYOUT_ENTITY_TYPE;
    }

    public function getCacheTags(string $entityId): array
    {
        return [CategoryRoute::buildName($entityId)];
    }

    public function getContentLayoutDefaultConfigKey(): string
    {
        return self::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT;
    }

    public function getPageDataRequirements(): array
    {
        return [
            ...parent::getPageDataRequirements(),
            new DataRequirement(
                'productListing',
                ProductListingDataLoader::SOURCE,
                new ProductListingLoaderConfig(
                    property: 'categoryId',
                    associations: ['cover', 'cover.media', 'cover.media.thumbnails', 'manufacturer'],
                )
            ),
        ];
    }

    protected function getEntityAssociations(): array
    {
        return ['media', 'translations'];
    }

    protected function defineEntityIdField(): IdField
    {
        return new IdField('category_id', 'categoryId');
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductContentLayout;

use Shopwell\Core\Content\Product\ContentSystem\DataLoader\ProductConfiguratorDataLoader;
use Shopwell\Core\Content\Product\ContentSystem\DataLoader\ProductConfiguratorLoaderConfig;
use Shopwell\Core\Content\Product\ContentSystem\DataLoader\ProductReviewDataLoader;
use Shopwell\Core\Content\Product\ContentSystem\DataLoader\ProductReviewLoaderConfig;
use Shopwell\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopwell\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class ProductContentLayoutDefinition extends AbstractContentLayoutAssignableDefinition
{
    final public const ENTITY_NAME = 'product_content_layout';

    final public const CONTENT_LAYOUT_ENTITY_TYPE = 'product';

    final public const CONFIG_KEY_DEFAULT_CONTENT_LAYOUT = 'core.content_system.default_product_content_layout';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ProductContentLayoutEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ProductContentLayoutCollection::class;
    }

    public function getContentLayoutEntityType(): string
    {
        return self::CONTENT_LAYOUT_ENTITY_TYPE;
    }

    public function getCacheTags(string $entityId): array
    {
        return [EntityCacheKeyGenerator::buildProductTag($entityId)];
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
                'configuratorSettings',
                ProductConfiguratorDataLoader::SOURCE,
                new ProductConfiguratorLoaderConfig(productId: 'productId'),
            ),
            new DataRequirement(
                'reviews',
                ProductReviewDataLoader::SOURCE,
                new ProductReviewLoaderConfig(property: 'productId')
            ),
        ];
    }

    protected function getEntityAssociations(): array
    {
        return [
            'manufacturer.media',
            'guaranteeTermsMedia',
            'options.group',
            'properties.group',
            'mainCategories.category',
            'media.media',
            // The picture behind the cover assignment, which `product.cover` needs to project to a
            // MediaEntity. SalesChannelProductDefinition::processCriteria() happens to add this one too, but
            // it adds it for the storefront's own reasons and only while the criteria selects no fields —
            // too conditional for a mapping candidate to rest on, so the requirement is declared here.
            'cover.media',
        ];
    }

    protected function defineEntityIdField(): IdField
    {
        return new IdField('product_id', 'productId');
    }
}

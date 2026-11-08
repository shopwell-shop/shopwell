<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Cache;

use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\LandingPage\LandingPageDefinition;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\ProductStream\ProductStreamDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\Log\Package;

/**
 * Resolves cache tags for entities based on the existing cache invalidation system.
 *
 * Only entities with defined cache tag patterns are supported. For unsupported
 * entities, null is returned, indicating the page should not be cached.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class EntityCacheTagResolver
{
    /**
     * @var array<string, string>
     */
    private const TAG_PATTERNS = [
        ProductDefinition::ENTITY_NAME => 'product-',
        CategoryDefinition::ENTITY_NAME => 'category-route-',
        LandingPageDefinition::ENTITY_NAME => 'landing-page-route-',
        ProductStreamDefinition::ENTITY_NAME => 'product-stream-',
    ];

    public function resolve(EntityDefinition $definition, string $primaryKey): ?string
    {
        $entityName = $definition->getEntityName();

        $prefix = self::TAG_PATTERNS[$entityName] ?? null;

        if ($prefix === null) {
            return null;
        }

        return $prefix . $primaryKey;
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Category\Service;

use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Category\CategoryEntity;
use Shopwell\Core\Content\LandingPage\LandingPageDefinition;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;

#[Package('discovery')]
class CategoryUrlGenerator extends AbstractCategoryUrlGenerator
{
    /**
     * @internal
     */
    public function __construct(private readonly EntityRouteResolver $entityRouteResolver)
    {
    }

    public function getDecorated(): AbstractCategoryUrlGenerator
    {
        throw new DecorationPatternException(self::class);
    }

    public function generate(CategoryEntity $category, ?SalesChannelEntity $salesChannel): ?string
    {
        if ($category->getType() === CategoryDefinition::TYPE_FOLDER) {
            return null;
        }

        $salesChannelTypeId = $salesChannel?->getTypeId();

        if ($category->getType() !== CategoryDefinition::TYPE_LINK) {
            return $this->entityRouteResolver->generateSeoUrlPlaceholder(CategoryDefinition::ENTITY_NAME, $category->getId(), $salesChannelTypeId);
        }

        $linkType = $category->getTranslation('linkType');
        $internalLink = $category->getTranslation('internalLink');

        if (!$internalLink && $linkType && $linkType !== CategoryDefinition::LINK_TYPE_EXTERNAL) {
            return null;
        }

        switch ($linkType) {
            case CategoryDefinition::LINK_TYPE_PRODUCT:
                return $this->entityRouteResolver->generateSeoUrlPlaceholder(ProductDefinition::ENTITY_NAME, $internalLink, $salesChannelTypeId);

            case CategoryDefinition::LINK_TYPE_CATEGORY:
                return $this->entityRouteResolver->generateSeoUrlPlaceholder(CategoryDefinition::ENTITY_NAME, $internalLink, $salesChannelTypeId);

            case CategoryDefinition::LINK_TYPE_LANDING_PAGE:
                return $this->entityRouteResolver->generateSeoUrlPlaceholder(LandingPageDefinition::ENTITY_NAME, $internalLink, $salesChannelTypeId);

            case CategoryDefinition::LINK_TYPE_EXTERNAL:
            default:
                return $category->getTranslation('externalLink');
        }
    }
}

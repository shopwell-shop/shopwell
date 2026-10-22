<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Cms\DataResolver\Element;

use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryEntity;
use Shopwell\Core\Content\Blog\SalesChannel\BlogListingStruct;
use Shopwell\Core\Content\Blog\Service\BlogListingReader;
use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopwell\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopwell\Core\Framework\Log\Package;

/**
 * The `blog-listing` cms element.
 *
 * It keeps no query of its own: the assignment of "which entries belong to which page" is already made
 * by {@see BlogListingReader}, and doing it a second time here is how two listings of the same shop end
 * up sorted differently. The element only decides the density and where the entries come from:
 *
 * - placed on a blog category page, it renders the entries of that category;
 * - placed anywhere else, it renders the newest published entries.
 *
 * The layout is the single configuration field, with `classic` as the default when an editor drops the
 * element on a page and never opens its settings. The amount of entries is the shop setting and not a
 * field of its own, so a merchant who changes "posts per page" changes the page and the element with
 * one switch.
 */
#[Package('discovery')]
class BlogListingCmsElementResolver extends AbstractCmsElementResolver
{
    final public const TYPE = 'blog-listing';

    /**
     * @internal
     */
    public function __construct(private readonly BlogListingReader $listingReader)
    {
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    /**
     * The entries are not collected through the cms criteria pipeline: they are read through the same
     * loader the listing page uses, so visibility, translation and ordering are applied once.
     */
    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $layoutConfig = $slot->getFieldConfig()->get('layout');
        $layout = $layoutConfig?->getStringValue() ?? BlogListingStruct::LAYOUT_CLASSIC;

        $page = max(1, (int) $resolverContext->getRequest()->query->get('p', '1'));

        $slot->setData(
            $this->listingReader->listing(
                $layout,
                $this->categoryId($resolverContext),
                $page,
                $resolverContext->getSalesChannelContext(),
            )
        );
    }

    /**
     * The category the element is placed on, if any. On an article or a landing page there is none and
     * the element falls back to the newest entries.
     */
    private function categoryId(ResolverContext $resolverContext): ?string
    {
        if (!$resolverContext instanceof EntityResolverContext) {
            return null;
        }

        $entity = $resolverContext->getEntity();

        return $entity instanceof BlogCategoryEntity ? $entity->id : null;
    }
}

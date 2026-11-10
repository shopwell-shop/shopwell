<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout;

use Shopwell\Core\Content\Blog\SalesChannel\BlogListingStruct;
use Shopwell\Core\Content\Blog\Service\BlogListingReader;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<BlogListingStruct>
 */
#[Package('discovery')]
class BlogListingDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'blog_listing';

    public function __construct(private readonly BlogListingReader $reader)
    {
    }

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: true),
        ]);
    }

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        $categoryId = strtolower($inputs->stringOrNull('property') ?? '');
        if ($categoryId !== '' && !Uuid::isValid($categoryId)) {
            return ContentDataLoaderResult::notFound();
        }

        try {
            $listing = $this->reader->listing(BlogListingStruct::LAYOUT_CLASSIC, $categoryId !== '' ? $categoryId : null, max(1, $request->query->getInt('p', 1)), $context);
        } catch (ShopwellHttpException) {
            return ContentDataLoaderResult::notFound();
        }

        // Publication schedules can change the visible entries without a write.
        return ContentDataLoaderResult::uncacheable($listing);
    }
}

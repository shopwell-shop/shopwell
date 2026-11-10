<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout;

use Shopwell\Core\Content\Blog\Service\BlogDetailReader;
use Shopwell\Core\Content\Blog\Service\BlogDetailStruct;
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
 * @extends AbstractContentDataLoader<BlogDetailStruct>
 */
#[Package('discovery')]
class BlogDetailDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'blog_detail';

    public function __construct(private readonly BlogDetailReader $reader)
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
        $blogId = strtolower($inputs->stringOrNull('property') ?? '');
        if (!Uuid::isValid($blogId)) {
            return ContentDataLoaderResult::notFound();
        }

        try {
            $detail = $this->reader->read(
                $blogId,
                $request,
                $context,
                includeUnpublished: $request->attributes->get(BlogSpecificationSource::PREVIEW_ATTRIBUTE) === true,
            );
        } catch (ShopwellHttpException) {
            return ContentDataLoaderResult::notFound();
        }

        // The reader includes the current comment page and customer-specific form state.
        return ContentDataLoaderResult::uncacheable($detail);
    }
}

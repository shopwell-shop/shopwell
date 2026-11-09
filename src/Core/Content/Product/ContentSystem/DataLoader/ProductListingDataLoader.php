<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\ContentSystem\DataLoader;

use Shopwell\Core\Content\Product\ProductException;
use Shopwell\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\String\u;

/**
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<ProductListingResult>
 */
#[Package('framework')]
class ProductListingDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'product_listing';

    public function __construct(
        private readonly AbstractProductListingRoute $listingRoute
    ) {
    }

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: 'navigationId'),
            new ConfigKeySpecification('associations', ConfigKeyKind::Literal, 'list<string>', required: false, hasDefault: true, default: []),
            new ConfigKeySpecification('associationOverride', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: 'associations', referencedType: 'list<string>', mergesInto: 'associations'),
        ]);
    }

    public function load(
        LoaderInputs $inputs,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $navigationId = $inputs->stringOrNull('property');

        if ($navigationId === null) {
            return ContentDataLoaderResult::notFound();
        }

        $navigationId = u($navigationId)->lower()->toString();

        // An unsubstituted placeholder such as "{{categoryId}}" passes LoaderInputResolver::dereference()
        // untouched; guard after the lowercase (Uuid::VALID_PATTERN is lowercase-only) instead of reaching
        // Uuid::fromHexToBytes() when ProductListingRoute searches the category by id.
        if (!Uuid::isValid($navigationId)) {
            return ContentDataLoaderResult::notFound();
        }

        $criteria = $this->buildCriteria($inputs);

        // Ordinary ShopwellHttpException outcomes degrade the element to notFound(); everything else, such as a \TypeError
        // or a database driver failure, propagates. Why the catch is the covering ancestor and never an
        // enumerated union: src/Core/Framework/ContentSystem/Hydration/DataLoader/README.md#degradation-boundary
        // Known local throws: a category assigned to a deleted or filterless product stream surfaces as
        // EntityNotFoundException or NoFilterException out of ProductStreamBuilder.
        try {
            $response = $this->listingRoute->load($navigationId, $request, $context, $criteria);
        } catch (ShopwellHttpException $exception) {
            if ($exception instanceof ProductException && $exception->is(ProductException::LISTING_PAGE_OUT_OF_RANGE)) {
                throw $exception;
            }

            return ContentDataLoaderResult::notFound();
        }

        // ProductListingRoute internally adds cache tags via CacheTagCollector
        return ContentDataLoaderResult::cachedExternally($response->getResult());
    }

    /**
     * The `associationOverride` reference is already folded into `associations` by the input resolver.
     */
    private function buildCriteria(LoaderInputs $inputs): Criteria
    {
        $criteria = new Criteria();

        foreach ($inputs->stringList('associations') as $association) {
            $criteria->addAssociation($association);
        }

        return $criteria;
    }
}

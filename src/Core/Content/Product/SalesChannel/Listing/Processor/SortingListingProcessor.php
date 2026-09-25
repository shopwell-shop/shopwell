<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\Listing\Processor;

use Shopwell\Core\Content\Product\Events\ProductListingCollectSortingEvent;
use Shopwell\Core\Content\Product\ProductException;
use Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopwell\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopwell\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopwell\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('inventory')]
class SortingListingProcessor extends AbstractListingProcessor
{
    /**
     * Transports the collected sortings from prepare() to process(). Not an extension point:
     * use ProductListingCollectSortingEvent to add or remove sortings, and
     * ProductListingResult::getAvailableSortings() to read them.
     *
     * @internal
     */
    final public const SORTINGS_EXTENSION = 'sortings';

    /**
     * @param EntityRepository<ProductSortingCollection> $sortingRepository
     *
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $sortingRepository,
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    public function getDecorated(): AbstractListingProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function prepare(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        if (!RequestParamHelper::get($request, 'order')) {
            $key = RequestParamHelper::get($request, 'search') ? 'core.listing.defaultSearchResultSorting' : 'core.listing.defaultSorting';
            $request->request->set('order', $this->getDefaultSortingKey($key, $context));
        }

        /** @var ProductSortingCollection $sortings */
        $sortings = $criteria->getExtension(self::SORTINGS_EXTENSION) ?? new ProductSortingCollection();
        $sortings->merge($this->getAvailableSortings($request, $context->getContext()));

        $this->dispatcher->dispatch(new ProductListingCollectSortingEvent($request, $sortings, $context));

        $currentSorting = $this->getCurrentSorting($sortings, $request, $context->getSalesChannelId());

        if ($currentSorting !== null) {
            $fallbackSorting = null;
            if ($this->hasQueriesOrTerm($criteria)) {
                $fallbackSorting = new FieldSorting(Criteria::SCORE_FIELD, FieldSorting::DESCENDING);
            }

            $criteria->addSorting(
                ...$currentSorting->createDalSorting($fallbackSorting)
            );
        }

        $criteria->addExtension(self::SORTINGS_EXTENSION, $sortings);
    }

    public function process(Request $request, ProductListingResult $result, SalesChannelContext $context): void
    {
        /** @var ProductSortingCollection $sortings */
        $sortings = $result->getCriteria()->getExtension(self::SORTINGS_EXTENSION);
        $currentSorting = $this->getCurrentSorting($sortings, $request, $context->getSalesChannelId());

        if ($currentSorting !== null) {
            $result->setSorting($currentSorting->getKey());
        }

        $result->setAvailableSortings($sortings);
    }

    private function hasQueriesOrTerm(Criteria $criteria): bool
    {
        return $criteria->getQueries() !== [] || $criteria->getTerm();
    }

    private function getCurrentSorting(ProductSortingCollection $sortings, Request $request, string $salesChannelId): ?ProductSortingEntity
    {
        $key = RequestParamHelper::get($request, 'order');

        if (!\is_string($key)) {
            throw ProductException::sortingNotFoundException('');
        }

        $sorting = $sortings->getByKey($key);
        if ($sorting !== null) {
            return $sorting;
        }

        return $sortings->get($this->systemConfigService->getString('core.listing.defaultSorting', $salesChannelId));
    }

    private function getAvailableSortings(Request $request, Context $context): ProductSortingCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('product-listing::load-sortings');

        $availableSortings = RequestParamHelper::get($request, 'availableSortings');
        $availableSortingsById = [];

        if (\is_array($availableSortings)) {
            $prioritiesById = [];
            foreach ($availableSortings as $id => $priority) {
                if (\is_string($id) && Uuid::isValid($id)) {
                    // non-numeric priorities sort as 0, matching SORT_NUMERIC's coercion
                    $prioritiesById[$id] = \is_numeric($priority) ? (float) $priority : 0.0;
                }
            }

            if ($prioritiesById !== []) {
                arsort($prioritiesById);
                $availableSortingsById = array_keys($prioritiesById);

                $criteria->addFilter(new EqualsAnyFilter('id', $availableSortingsById));
            }
        }

        $criteria
            ->addFilter(new EqualsFilter('active', true))
            ->addSorting(new FieldSorting('priority', 'DESC'));

        $sortings = $this->sortingRepository->search($criteria, $context)->getEntities();

        if ($availableSortingsById) {
            $sortings->sortByIdArray($availableSortingsById);
        }

        return $sortings;
    }

    private function getDefaultSortingKey(string $key, SalesChannelContext $context): ?string
    {
        $id = $this->systemConfigService->getString($key, $context->getSalesChannelId());

        if (!Uuid::isValid($id)) {
            return $id;
        }

        $criteria = new Criteria([$id]);

        return $this->sortingRepository->search($criteria, $context->getContext())->getEntities()->first()?->get('key');
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Cms\ProductSlider;

use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopwell\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopwell\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfig;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopwell\Core\Content\Cms\SalesChannel\Struct\ProductSliderStruct;
use Shopwell\Core\Content\Product\Events\ProductSliderStaticCriteriaEvent;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('discovery')]
class StaticProductProcessor extends AbstractProductSliderProcessor
{
    private const STATIC_SEARCH_KEY = 'product-slider';

    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getDecorated(): AbstractProductSliderProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function getSource(): string
    {
        return 'static';
    }

    public function collect(CmsSlotEntity $slot, FieldConfigCollection $config, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $products = $config->get('products');
        \assert($products instanceof FieldConfig);
        $criteria = new Criteria($products->getArrayValue());
        $criteria->addAssociation('options.group');

        $this->eventDispatcher->dispatch(new ProductSliderStaticCriteriaEvent($slot, $criteria, $resolverContext->getSalesChannelContext()));

        $collection = new CriteriaCollection();
        $collection->add(self::STATIC_SEARCH_KEY . '_' . $slot->getUniqueIdentifier(), ProductDefinition::class, $criteria);

        return $collection;
    }

    public function enrich(CmsSlotEntity $slot, ElementDataCollection $result, ResolverContext $resolverContext): void
    {
        $key = self::STATIC_SEARCH_KEY . '_' . $slot->getUniqueIdentifier();
        $searchResult = $result->get($key);

        if (!$searchResult) {
            return;
        }

        $products = $searchResult->getEntities();
        if (!$products instanceof ProductCollection) {
            return;
        }

        $context = $resolverContext->getSalesChannelContext();

        if ($this->hideUnavailableProducts($context)) {
            $products = $this->filterOutOutOfStockHiddenCloseoutProducts($products);
        }

        $criteriaIds = array_unique($searchResult->getCriteria()->getIds());
        if ($criteriaIds !== [] && $searchResult->getCriteria()->getSorting() === []) {
            $configuredIds = $slot->getFieldConfig()->get('products')?->getArrayValue() ?? [];
            usort(
                $criteriaIds,
                static fn (string $a, string $b): int => array_search($a, $configuredIds, true) <=> array_search($b, $configuredIds, true)
            );
            $products->sortByIdArray($criteriaIds);
        }

        $slider = new ProductSliderStruct();
        $slider->setProducts($products);

        $slot->setData($slider);
    }

    protected function hideUnavailableProducts(SalesChannelContext $context): bool
    {
        return $this->systemConfigService->getBool(
            'core.listing.hideCloseoutProductsWhenOutOfStock',
            $context->getSalesChannelId()
        );
    }
}

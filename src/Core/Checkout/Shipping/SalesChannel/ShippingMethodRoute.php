<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Shipping\SalesChannel;

use Shopwell\Core\Checkout\Shipping\Hook\ShippingMethodRouteHook;
use Shopwell\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopwell\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\Framework\Rule\RuleIdMatcher;
use Shopwell\Core\Framework\Script\Execution\ScriptExecutor;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ShippingMethodRoute extends AbstractShippingMethodRoute
{
    final public const ALL_TAG = 'shipping-method-route';

    /**
     * @param SalesChannelRepository<ShippingMethodCollection> $shippingMethodRepository
     *
     * @internal
     */
    public function __construct(
        private readonly SalesChannelRepository $shippingMethodRepository,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly ScriptExecutor $scriptExecutor,
        private readonly RuleIdMatcher $ruleIdMatcher,
    ) {
    }

    public function getDecorated(): AbstractShippingMethodRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public static function buildName(string $salesChannelId): string
    {
        return 'shipping-method-route-' . $salesChannelId;
    }

    /**
     * Though this is a GET route, caching was not added as the output may be altered depending on dynamic rules,
     * which is not taken into account during the cache hash calculation.
     */
    #[Route(
        path: '/store-api/shipping-method',
        name: 'store-api.shipping.method',
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => ShippingMethodDefinition::ENTITY_NAME],
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ShippingMethodRouteResponse
    {
        $this->cacheTagCollector->addTag(self::buildName($context->getSalesChannelId()));

        $onlyAvailable = $request->query->getBoolean('onlyAvailable') || $request->request->getBoolean('onlyAvailable');

        $criteria
            ->addFilter(new EqualsFilter('active', true))
            ->addAssociation('media');

        if ($onlyAvailable) {
            // Any bound excludes rows without currency values, which cannot resolve shipping costs
            $criteria->addFilter(new RangeFilter('prices.currencyPrice', [RangeFilter::GTE => -\PHP_INT_MAX]));
        }

        if ($criteria->getSorting() === []) {
            $criteria->addSorting(new FieldSorting('position'), new FieldSorting('name', FieldSorting::ASCENDING));
        }

        $result = $this->shippingMethodRepository->search($criteria, $context);

        $shippingMethods = $result->getEntities();
        $shippingMethods->sortShippingMethodsByPreference($context);

        if ($onlyAvailable) {
            $shippingMethods = $this->ruleIdMatcher->filterCollection($shippingMethods, $context->getRuleIds());
        }

        $result->assign(['entities' => $shippingMethods, 'elements' => $shippingMethods->getElements(), 'total' => $shippingMethods->count()]);

        $this->scriptExecutor->execute(new ShippingMethodRouteHook(
            $shippingMethods,
            $onlyAvailable,
            $context,
        ));

        return new ShippingMethodRouteResponse($result);
    }
}

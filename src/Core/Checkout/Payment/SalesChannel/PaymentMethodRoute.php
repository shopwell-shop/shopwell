<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\SalesChannel;

use Shopwell\Core\Checkout\Payment\Extension\PaymentMethodRouteExtension;
use Shopwell\Core\Checkout\Payment\Gateway\PaymentMethodCollectability;
use Shopwell\Core\Checkout\Payment\Hook\PaymentMethodRouteHook;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
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
class PaymentMethodRoute extends AbstractPaymentMethodRoute
{
    final public const ALL_TAG = 'payment-method-route';

    /**
     * @internal
     *
     * @param SalesChannelRepository<PaymentMethodCollection> $paymentMethodRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $paymentMethodRepository,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly ScriptExecutor $scriptExecutor,
        private readonly RuleIdMatcher $ruleIdMatcher,
        private readonly ExtensionDispatcher $extensions,
        private readonly PaymentMethodCollectability $collectability,
    ) {
    }

    public function getDecorated(): AbstractPaymentMethodRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public static function buildName(string $salesChannelId): string
    {
        return 'payment-method-route-' . $salesChannelId;
    }

    /**
     * Though this is a GET route, caching was not added as the output may be altered depending on dynamic rules,
     * which is not taken into account during the cache hash calculation.
     * Given an `orderId`, the route evaluates an existing order of the customer instead of the current session.
     * Filtering by availability still requires `onlyAvailable`.
     */
    #[Route(
        path: '/store-api/payment-method',
        name: 'store-api.payment.method',
        defaults: [
            PlatformRequest::ATTRIBUTE_ENTITY => PaymentMethodDefinition::ENTITY_NAME,
            PlatformRequest::ATTRIBUTE_ALLOW_ORDER_RESTORATION => true,
        ],
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): PaymentMethodRouteResponse
    {
        return $this->extensions->publish(
            name: PaymentMethodRouteExtension::NAME,
            extension: new PaymentMethodRouteExtension($request, $context, $criteria),
            function: $this->_load(...),
        );
    }

    private function _load(Request $request, SalesChannelContext $context, Criteria $criteria): PaymentMethodRouteResponse
    {
        $this->cacheTagCollector->addTag(self::buildName($context->getSalesChannelId()));

        $criteria
            ->addFilter(new EqualsFilter('active', true))
            ->addSorting(new FieldSorting('position'))
            ->addAssociation('media');

        $result = $this->paymentMethodRepository->search($criteria, $context);

        $paymentMethods = $result->getEntities();
        $paymentMethods->sortPaymentMethodsByPreference($context);

        if ($request->query->getBoolean('onlyAvailable') || $request->request->getBoolean('onlyAvailable')) {
            // `filterCollection` is typed on the generic collection, but it filters in place and keeps
            // the class, so the result is the same collection it was handed
            /** @var PaymentMethodCollection $paymentMethods */
            $paymentMethods = $this->ruleIdMatcher->filterCollection($paymentMethods, $context->getRuleIds());
        }

        // a payment method that collects through the gateway but cannot collect right now is not a
        // merchandising decision: picking it would throw at the end of the checkout instead of paying,
        // so it is removed regardless of `onlyAvailable`
        $paymentMethods = $this->collectability->filter(
            $paymentMethods,
            $context->getContext(),
            $context->getCurrency()->getIsoCode(),
            $this->deviceOf($request)
        );

        $result->assign(['entities' => $paymentMethods, 'elements' => $paymentMethods->getElements(), 'total' => $paymentMethods->count()]);

        $this->scriptExecutor->execute(new PaymentMethodRouteHook(
            $paymentMethods,
            $request->query->getBoolean('onlyAvailable') || $request->request->getBoolean('onlyAvailable'),
            $context,
        ));

        return new PaymentMethodRouteResponse($result);
    }

    /**
     * The channels are filtered by device, so a phone is not offered a desktop only channel. The
     * parameter is optional: a request that does not say what it runs on keeps every channel, because
     * guessing a device would take a working method away from the device it was declared for.
     */
    private function deviceOf(Request $request): ?string
    {
        $device = $request->query->get('device');

        return \is_string($device) && $device !== '' ? strtolower($device) : null;
    }
}

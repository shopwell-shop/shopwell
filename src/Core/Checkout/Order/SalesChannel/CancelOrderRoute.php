<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\SalesChannel;

use Shopwell\Core\Checkout\Order\Extension\CancelOrderRouteExtension;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderException;
use Shopwell\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class CancelOrderRoute extends AbstractCancelOrderRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly OrderService $orderService,
        private readonly EntityRepository $orderRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractCancelOrderRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/order/state/cancel',
        name: 'store-api.order.state.cancel',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function cancel(Request $request, SalesChannelContext $context): CancelOrderRouteResponse
    {
        return $this->extensions->publish(
            name: CancelOrderRouteExtension::NAME,
            extension: new CancelOrderRouteExtension($request, $context),
            function: $this->_cancel(...),
        );
    }

    private function _cancel(Request $request, SalesChannelContext $context): CancelOrderRouteResponse
    {
        if (!$this->systemConfigService->getBool('core.cart.enableOrderRefunds', $context->getSalesChannelId())) {
            throw OrderException::orderNotCancellable();
        }

        $orderId = RequestParamHelper::get($request, 'orderId');

        if (!$orderId) {
            throw OrderException::invalidRequestParameter('orderId');
        }

        $this->verify($orderId, $context);

        $newState = $this->orderService->orderStateTransition(
            $orderId,
            'cancel',
            new ParameterBag(),
            $context->getContext()
        );

        return new CancelOrderRouteResponse($newState);
    }

    private function verify(string $orderId, SalesChannelContext $context): void
    {
        if (!$context->getCustomer()) {
            throw OrderException::customerNotLoggedIn();
        }

        $criteria = (new Criteria([$orderId]))
            ->addFilter(new EqualsFilter('orderCustomer.customerId', $context->getCustomerId()));

        $total = $this->orderRepository->searchIds($criteria, $context->getContext())->getTotal();
        if ($total === 0) {
            throw OrderException::orderNotFound($orderId);
        }
    }
}

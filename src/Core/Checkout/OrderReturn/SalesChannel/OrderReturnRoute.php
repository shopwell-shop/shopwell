<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\SalesChannel;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnCollection;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnException;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnService;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The returns of a logged in customer.
 *
 * Every method only answers for the customer of the context: an id in the path is checked against the
 * customer before it is used, so one customer can not read or change the return of another by
 * guessing an id. The rules themselves live in {@see OrderReturnService} - this route is the
 * transport and nothing else.
 *
 * Each endpoint publishes its {@see OrderReturnRouteExtension} first, so a plugin can answer a
 * request the core refuses or change the list it would return. The published contract always carries
 * the request, the context and the customer - a plugin still has to be able to see what was asked
 * for - but the id of the path is not part of it: the operation is handed the request and the context
 * only when it needs the request, and the id always by the endpoint itself.
 */
#[Package('after-sales')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class OrderReturnRoute
{
    private const LIST_LIMIT = 50;

    /**
     * @internal
     *
     * @param EntityRepository<OrderReturnCollection> $orderReturnRepository
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly OrderReturnService $orderReturnService,
        private readonly EntityRepository $orderReturnRepository,
        private readonly EntityRepository $orderRepository,
        private readonly OrderReturnPresenter $presenter,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    #[Route(
        path: '/store-api/order-return',
        name: 'store-api.order-return.load',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_GET]
    )]
    /**
     * @throws OrderReturnException
     */
    public function load(Request $request, SalesChannelContext $context, CustomerEntity $customer): OrderReturnRouteResponse
    {
        return $this->extensions->publish(
            name: OrderReturnRouteExtension::NAME,
            extension: new OrderReturnRouteExtension($request, $context, $customer),
            function: $this->_load(...),
        );
    }

    #[Route(
        path: '/store-api/order-return/positions/{orderId}',
        name: 'store-api.order-return.positions',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_GET]
    )]
    /**
     * @throws OrderReturnException
     */
    public function positions(Request $request, string $orderId, SalesChannelContext $context, CustomerEntity $customer): OrderReturnRouteResponse
    {
        return $this->extensions->publish(
            name: OrderReturnRouteExtension::NAME,
            extension: new OrderReturnRouteExtension($request, $context, $customer),
            function: fn (Request $request, SalesChannelContext $context, CustomerEntity $customer): OrderReturnRouteResponse => $this->_positions($orderId, $context, $customer),
        );
    }

    #[Route(
        path: '/store-api/order-return/{id}',
        name: 'store-api.order-return.detail',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_GET]
    )]
    /**
     * @throws OrderReturnException
     */
    public function detail(Request $request, string $id, SalesChannelContext $context, CustomerEntity $customer): OrderReturnRouteResponse
    {
        return $this->extensions->publish(
            name: OrderReturnRouteExtension::NAME,
            extension: new OrderReturnRouteExtension($request, $context, $customer),
            function: fn (Request $request, SalesChannelContext $context, CustomerEntity $customer): OrderReturnRouteResponse => $this->_detail($id, $context, $customer),
        );
    }

    #[Route(
        path: '/store-api/order-return',
        name: 'store-api.order-return.create',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    /**
     * @throws OrderReturnException
     */
    public function create(Request $request, SalesChannelContext $context, CustomerEntity $customer): OrderReturnRouteResponse
    {
        return $this->extensions->publish(
            name: OrderReturnRouteExtension::NAME,
            extension: new OrderReturnRouteExtension($request, $context, $customer),
            function: $this->_create(...),
        );
    }

    #[Route(
        path: '/store-api/order-return/{id}/cancel',
        name: 'store-api.order-return.cancel',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    /**
     * @throws OrderReturnException
     */
    public function cancel(Request $request, string $id, SalesChannelContext $context, CustomerEntity $customer): OrderReturnRouteResponse
    {
        return $this->extensions->publish(
            name: OrderReturnRouteExtension::NAME,
            extension: new OrderReturnRouteExtension($request, $context, $customer),
            function: fn (Request $request, SalesChannelContext $context, CustomerEntity $customer): OrderReturnRouteResponse => $this->_cancel($id, $context, $customer),
        );
    }

    #[Route(
        path: '/store-api/order-return/{id}/ship',
        name: 'store-api.order-return.ship',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    /**
     * @throws OrderReturnException
     */
    public function ship(Request $request, string $id, SalesChannelContext $context, CustomerEntity $customer): OrderReturnRouteResponse
    {
        return $this->extensions->publish(
            name: OrderReturnRouteExtension::NAME,
            extension: new OrderReturnRouteExtension($request, $context, $customer),
            function: fn (Request $request, SalesChannelContext $context, CustomerEntity $customer): OrderReturnRouteResponse => $this->_ship($request, $id, $context, $customer),
        );
    }

    #[Route(
        path: '/store-api/order-return/{id}/message',
        name: 'store-api.order-return.message',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    /**
     * @throws OrderReturnException
     */
    public function message(Request $request, string $id, SalesChannelContext $context, CustomerEntity $customer): OrderReturnRouteResponse
    {
        return $this->extensions->publish(
            name: OrderReturnRouteExtension::NAME,
            extension: new OrderReturnRouteExtension($request, $context, $customer),
            function: fn (Request $request, SalesChannelContext $context, CustomerEntity $customer): OrderReturnRouteResponse => $this->_message($request, $id, $context, $customer),
        );
    }

    private function _load(Request $request, SalesChannelContext $context, CustomerEntity $customer): OrderReturnRouteResponse
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('customerId', $customer->getId()),
            new EqualsFilter('salesChannelId', $context->getSalesChannelId())
        );
        $criteria->addAssociation('lineItems.orderLineItem');
        $criteria->addAssociation('stateMachineState');
        $criteria->addAssociation('reason');
        $criteria->addAssociation('order.currency');
        $criteria->addAssociation('messages');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit(min($request->query->getInt('limit', self::LIST_LIMIT), self::LIST_LIMIT));

        $orderReturns = $this->orderReturnRepository->search($criteria, $context->getContext())->getEntities();

        return new OrderReturnRouteResponse(new ArrayStruct([
            'orderReturns' => $this->presenter->presentList($orderReturns),
        ]));
    }

    private function _positions(string $orderId, SalesChannelContext $context, CustomerEntity $customer): OrderReturnRouteResponse
    {
        $order = $this->ownedOrder($orderId, $customer, $context);

        return new OrderReturnRouteResponse(new ArrayStruct([
            'orderNumber' => $order->getOrderNumber(),
            'positions' => $this->orderReturnService->returnablePositions($order, $context->getContext()),
            'reasons' => $this->orderReturnService->activeReasons($context->getContext()),
        ]));
    }

    private function _detail(string $id, SalesChannelContext $context, CustomerEntity $customer): OrderReturnRouteResponse
    {
        $orderReturn = $this->ownedReturn($id, $customer, $context);

        return new OrderReturnRouteResponse(new ArrayStruct([
            'orderReturn' => $this->presenter->present($orderReturn),
        ]));
    }

    private function _create(Request $request, SalesChannelContext $context, CustomerEntity $customer): OrderReturnRouteResponse
    {
        $orderReturn = $this->orderReturnService->create(
            [
                'orderId' => (string) $request->request->get('orderId', ''),
                'type' => (string) $request->request->get('type', ''),
                'positions' => $this->positionsOf($request),
                'reasonId' => $this->stringOrNull($request, 'reasonId'),
                'reasonText' => $this->stringOrNull($request, 'reasonText'),
                'customerNote' => $this->stringOrNull($request, 'customerNote'),
                'evidence' => $this->stringList($request->request->all('evidence')),
            ],
            $context->getContext(),
            $customer
        );

        return new OrderReturnRouteResponse(new ArrayStruct([
            'orderReturn' => $this->presenter->present($orderReturn),
        ]), Response::HTTP_CREATED);
    }

    private function _cancel(string $id, SalesChannelContext $context, CustomerEntity $customer): OrderReturnRouteResponse
    {
        $this->ownedReturn($id, $customer, $context);

        $orderReturn = $this->orderReturnService->cancel($id, $context->getContext());

        return new OrderReturnRouteResponse(new ArrayStruct([
            'orderReturn' => $this->presenter->present($orderReturn),
        ]));
    }

    private function _ship(Request $request, string $id, SalesChannelContext $context, CustomerEntity $customer): OrderReturnRouteResponse
    {
        $this->ownedReturn($id, $customer, $context);

        $trackingCode = trim((string) $request->request->get('trackingCode', ''));

        if ($trackingCode === '') {
            throw OrderReturnException::missingTrackingCode();
        }

        $orderReturn = $this->orderReturnService->ship(
            $id,
            $this->stringOrNull($request, 'trackingCarrier'),
            $trackingCode,
            $context->getContext()
        );

        return new OrderReturnRouteResponse(new ArrayStruct([
            'orderReturn' => $this->presenter->present($orderReturn),
        ]));
    }

    private function _message(Request $request, string $id, SalesChannelContext $context, CustomerEntity $customer): OrderReturnRouteResponse
    {
        $this->ownedReturn($id, $customer, $context);

        $content = trim((string) $request->request->get('content', ''));

        if ($content === '') {
            throw OrderReturnException::emptyMessage();
        }

        $this->orderReturnService->addMessage(
            $id,
            $content,
            OrderReturnEntity::AUTHOR_CUSTOMER,
            $customer->getId(),
            $this->stringList($request->request->all('attachments')),
            $context->getContext()
        );

        $orderReturn = $this->orderReturnService->get($id, $context->getContext());

        return new OrderReturnRouteResponse(new ArrayStruct([
            'orderReturn' => $this->presenter->present($orderReturn),
        ]));
    }

    /**
     * @return array<string, int> order line item id => quantity
     */
    private function positionsOf(Request $request): array
    {
        $positions = [];

        foreach ($request->request->all('positions') as $position) {
            if (!\is_array($position)) {
                continue;
            }

            $lineItemId = $position['lineItemId'] ?? null;

            if (!\is_string($lineItemId) || $lineItemId === '') {
                continue;
            }

            $positions[$lineItemId] = (int) ($position['quantity'] ?? 0);
        }

        return $positions;
    }

    private function stringOrNull(Request $request, string $key): ?string
    {
        $value = $request->request->get($key);

        return \is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @param array<mixed> $values
     *
     * @return list<string>|null
     */
    private function stringList(array $values): ?array
    {
        $strings = array_values(array_filter($values, 'is_string'));

        return $strings === [] ? null : $strings;
    }

    private function ownedReturn(string $id, CustomerEntity $customer, SalesChannelContext $context): OrderReturnEntity
    {
        $orderReturn = $this->orderReturnService->get($id, $context->getContext());

        if ($orderReturn->customerId !== $customer->getId()) {
            // the same answer as an unknown id on purpose: a customer must not learn that the return of
            // somebody else exists
            throw OrderReturnException::notFound($id);
        }

        return $orderReturn;
    }

    private function ownedOrder(string $orderId, CustomerEntity $customer, SalesChannelContext $context): OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('orderCustomer');

        $order = $this->orderRepository->search($criteria, $context->getContext())->getEntities()->first();

        if (!$order instanceof OrderEntity || $order->getOrderCustomer()?->getCustomerId() !== $customer->getId()) {
            throw OrderReturnException::orderNotFound($orderId);
        }

        return $order;
    }
}

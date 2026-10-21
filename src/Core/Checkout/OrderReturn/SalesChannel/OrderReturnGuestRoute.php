<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\SalesChannel;

use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnCollection;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnException;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnGuestTokenService;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnService;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnSettings;
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
 * The returns of a customer who checked out as a guest.
 *
 * A guest has no account to log in to, so the order number and the mail address the order was placed
 * with are the only proof there is. They are verified once and exchanged for a signed token
 * ({@see OrderReturnGuestTokenService}); everything after that only carries the token, which keeps the
 * mail address out of every later request and the shop out of storing a session per guest.
 *
 * Nothing here tells an anonymous visitor whether an order exists: a wrong order number and a wrong
 * address answer the same way.
 *
 * Each endpoint publishes its {@see OrderReturnGuestRouteExtension} first, the same way the routes of
 * a logged in customer do. The published contract carries the request and the context; the id of the
 * path is handed to the operation by the endpoint itself.
 */
#[Package('after-sales')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class OrderReturnGuestRoute
{
    private const TOKEN_HEADER = 'x-order-return-token';

    /**
     * @internal
     *
     * @param EntityRepository<OrderReturnCollection> $orderReturnRepository
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly OrderReturnService $orderReturnService,
        private readonly OrderReturnGuestTokenService $tokenService,
        private readonly OrderReturnSettings $settings,
        private readonly EntityRepository $orderReturnRepository,
        private readonly EntityRepository $orderRepository,
        private readonly OrderReturnPresenter $presenter,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    #[Route(
        path: '/store-api/order-return/guest/verify',
        name: 'store-api.order-return.guest.verify',
        methods: [Request::METHOD_POST]
    )]
    /**
     * @throws OrderReturnException
     */
    public function verify(Request $request, SalesChannelContext $context): OrderReturnRouteResponse
    {
        return $this->extensions->publish(
            name: OrderReturnGuestRouteExtension::NAME,
            extension: new OrderReturnGuestRouteExtension($request, $context),
            function: $this->_verify(...),
        );
    }

    #[Route(
        path: '/store-api/order-return/guest',
        name: 'store-api.order-return.guest.load',
        methods: [Request::METHOD_GET]
    )]
    /**
     * @throws OrderReturnException
     */
    public function load(Request $request, SalesChannelContext $context): OrderReturnRouteResponse
    {
        return $this->extensions->publish(
            name: OrderReturnGuestRouteExtension::NAME,
            extension: new OrderReturnGuestRouteExtension($request, $context),
            function: $this->_load(...),
        );
    }

    #[Route(
        path: '/store-api/order-return/guest/{id}',
        name: 'store-api.order-return.guest.detail',
        methods: [Request::METHOD_GET]
    )]
    /**
     * @throws OrderReturnException
     */
    public function detail(Request $request, string $id, SalesChannelContext $context): OrderReturnRouteResponse
    {
        return $this->extensions->publish(
            name: OrderReturnGuestRouteExtension::NAME,
            extension: new OrderReturnGuestRouteExtension($request, $context),
            function: fn (Request $request, SalesChannelContext $context): OrderReturnRouteResponse => $this->_detail($request, $id, $context),
        );
    }

    #[Route(
        path: '/store-api/order-return/guest',
        name: 'store-api.order-return.guest.create',
        methods: [Request::METHOD_POST]
    )]
    /**
     * @throws OrderReturnException
     */
    public function create(Request $request, SalesChannelContext $context): OrderReturnRouteResponse
    {
        return $this->extensions->publish(
            name: OrderReturnGuestRouteExtension::NAME,
            extension: new OrderReturnGuestRouteExtension($request, $context),
            function: $this->_create(...),
        );
    }

    #[Route(
        path: '/store-api/order-return/guest/{id}/cancel',
        name: 'store-api.order-return.guest.cancel',
        methods: [Request::METHOD_POST]
    )]
    /**
     * @throws OrderReturnException
     */
    public function cancel(Request $request, string $id, SalesChannelContext $context): OrderReturnRouteResponse
    {
        return $this->extensions->publish(
            name: OrderReturnGuestRouteExtension::NAME,
            extension: new OrderReturnGuestRouteExtension($request, $context),
            function: fn (Request $request, SalesChannelContext $context): OrderReturnRouteResponse => $this->_cancel($request, $id, $context),
        );
    }

    #[Route(
        path: '/store-api/order-return/guest/{id}/ship',
        name: 'store-api.order-return.guest.ship',
        methods: [Request::METHOD_POST]
    )]
    /**
     * @throws OrderReturnException
     */
    public function ship(Request $request, string $id, SalesChannelContext $context): OrderReturnRouteResponse
    {
        return $this->extensions->publish(
            name: OrderReturnGuestRouteExtension::NAME,
            extension: new OrderReturnGuestRouteExtension($request, $context),
            function: fn (Request $request, SalesChannelContext $context): OrderReturnRouteResponse => $this->_ship($request, $id, $context),
        );
    }

    #[Route(
        path: '/store-api/order-return/guest/{id}/message',
        name: 'store-api.order-return.guest.message',
        methods: [Request::METHOD_POST]
    )]
    /**
     * @throws OrderReturnException
     */
    public function message(Request $request, string $id, SalesChannelContext $context): OrderReturnRouteResponse
    {
        return $this->extensions->publish(
            name: OrderReturnGuestRouteExtension::NAME,
            extension: new OrderReturnGuestRouteExtension($request, $context),
            function: fn (Request $request, SalesChannelContext $context): OrderReturnRouteResponse => $this->_message($request, $id, $context),
        );
    }

    private function _verify(Request $request, SalesChannelContext $context): OrderReturnRouteResponse
    {
        $orderNumber = trim((string) $request->request->get('orderNumber', ''));
        // the address is normalized before it travels in the token: the return is created with the
        // address of the token and compared to it later, so one spelling has to win - and the service
        // stores the lower case one
        $email = mb_strtolower(trim((string) $request->request->get('email', '')));

        if ($orderNumber === '' || $email === '') {
            throw OrderReturnException::orderNotFound($orderNumber);
        }

        $order = $this->findOrder($orderNumber, $email, $context);

        if (!$this->settings->allowGuest($order->getSalesChannelId())) {
            throw OrderReturnException::disabled();
        }

        $token = $this->tokenService->issue($order->getId(), $email);

        return new OrderReturnRouteResponse(new ArrayStruct([
            'token' => $token,
            'orderNumber' => $order->getOrderNumber(),
            'positions' => $this->orderReturnService->returnablePositions($order, $context->getContext()),
            'reasons' => $this->orderReturnService->activeReasons($context->getContext()),
        ]));
    }

    private function _load(Request $request, SalesChannelContext $context): OrderReturnRouteResponse
    {
        $guest = $this->resolveToken($request);

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('orderId', $guest['orderId']),
            new EqualsFilter('guestEmail', $guest['email'])
        );
        $criteria->addAssociation('lineItems.orderLineItem');
        $criteria->addAssociation('stateMachineState');
        $criteria->addAssociation('reason');
        $criteria->addAssociation('order.currency');
        $criteria->addAssociation('messages');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $orderReturns = $this->orderReturnRepository->search($criteria, $context->getContext())->getEntities();

        $order = $this->loadOrder($guest['orderId'], $context);

        return new OrderReturnRouteResponse(new ArrayStruct([
            'orderReturns' => $this->presenter->presentList($orderReturns),
            // the token proves the order, so what is left to return belongs in the same answer as the
            // returns that already exist - a second call could only go stale in between
            'orderNumber' => $order?->getOrderNumber(),
            'positions' => $order === null ? [] : $this->orderReturnService->returnablePositions($order, $context->getContext()),
            'reasons' => $this->orderReturnService->activeReasons($context->getContext()),
        ]));
    }

    private function _detail(Request $request, string $id, SalesChannelContext $context): OrderReturnRouteResponse
    {
        $orderReturn = $this->assertGuestOwns($id, $request, $context);

        return new OrderReturnRouteResponse(new ArrayStruct([
            'orderReturn' => $this->presenter->present($orderReturn),
        ]));
    }

    private function _create(Request $request, SalesChannelContext $context): OrderReturnRouteResponse
    {
        $guest = $this->resolveToken($request);

        $token = $this->tokenOf($request);

        $orderReturn = $this->orderReturnService->create(
            [
                'orderId' => $guest['orderId'],
                'type' => (string) $request->request->get('type', ''),
                'positions' => $this->positionsOf($request),
                'reasonId' => $this->stringOrNull($request, 'reasonId'),
                'reasonText' => $this->stringOrNull($request, 'reasonText'),
                'customerNote' => $this->stringOrNull($request, 'customerNote'),
                'evidence' => $this->stringList($request->request->all('evidence')),
                'guestEmail' => $guest['email'],
                'guestTokenHash' => $this->tokenService->hash($token),
            ],
            $context->getContext(),
            null
        );

        return new OrderReturnRouteResponse(new ArrayStruct([
            'orderReturn' => $this->presenter->present($orderReturn),
        ]), Response::HTTP_CREATED);
    }

    private function _cancel(Request $request, string $id, SalesChannelContext $context): OrderReturnRouteResponse
    {
        $this->assertGuestOwns($id, $request, $context);

        $orderReturn = $this->orderReturnService->cancel($id, $context->getContext());

        return new OrderReturnRouteResponse(new ArrayStruct([
            'orderReturn' => $this->presenter->present($orderReturn),
        ]));
    }

    private function _ship(Request $request, string $id, SalesChannelContext $context): OrderReturnRouteResponse
    {
        $this->assertGuestOwns($id, $request, $context);

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

    private function _message(Request $request, string $id, SalesChannelContext $context): OrderReturnRouteResponse
    {
        $this->assertGuestOwns($id, $request, $context);

        $content = trim((string) $request->request->get('content', ''));

        if ($content === '') {
            throw OrderReturnException::emptyMessage();
        }

        $this->orderReturnService->addMessage(
            $id,
            $content,
            OrderReturnEntity::AUTHOR_CUSTOMER,
            null,
            $this->stringList($request->request->all('attachments')),
            $context->getContext()
        );

        $orderReturn = $this->orderReturnService->get($id, $context->getContext());

        return new OrderReturnRouteResponse(new ArrayStruct([
            'orderReturn' => $this->presenter->present($orderReturn),
        ]));
    }

    /**
     * The order behind the token, with what the returnable positions need. An order that is gone is
     * answered with null and not with an error: the returns that already exist stay readable.
     */
    private function loadOrder(string $orderId, SalesChannelContext $context): ?OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('currency');

        $order = $this->orderRepository->search($criteria, $context->getContext())->getEntities()->first();

        return $order instanceof OrderEntity ? $order : null;
    }

    /**
     * @throws OrderReturnException
     */
    private function findOrder(string $orderNumber, string $email, SalesChannelContext $context): OrderEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('orderCustomer');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('stateMachineState');
        $criteria->addAssociation('deliveries.stateMachineState');
        $criteria->setLimit(1);

        $order = $this->orderRepository->search($criteria, $context->getContext())->getEntities()->first();

        if (!$order instanceof OrderEntity) {
            throw OrderReturnException::orderNotFound($orderNumber);
        }

        $orderEmail = mb_strtolower(trim((string) $order->getOrderCustomer()?->getEmail()));

        if ($orderEmail === '' || $orderEmail !== mb_strtolower($email)) {
            throw OrderReturnException::orderNotFound($orderNumber);
        }

        return $order;
    }

    /**
     * @return array{orderId: string, email: string}
     */
    private function resolveToken(Request $request): array
    {
        $token = $this->tokenOf($request);
        $guest = $this->tokenService->resolve($token);

        if ($guest === null) {
            throw OrderReturnException::guestAccessDenied();
        }

        return $guest;
    }

    private function assertGuestOwns(string $id, Request $request, SalesChannelContext $context): OrderReturnEntity
    {
        $guest = $this->resolveToken($request);
        $orderReturn = $this->orderReturnService->get($id, $context->getContext());

        if ($orderReturn->orderId !== $guest['orderId'] || $orderReturn->guestEmail !== $guest['email']) {
            throw OrderReturnException::guestAccessDenied();
        }

        return $orderReturn;
    }

    private function tokenOf(Request $request): string
    {
        $token = $request->headers->get(self::TOKEN_HEADER)
            ?? $request->request->get('token')
            ?? $request->query->get('token');

        return \is_string($token) ? $token : '';
    }

    /**
     * @return array<string, int>
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
}

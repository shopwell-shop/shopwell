<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\Api;

use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\SalesChannel\OrderReturnPresenter;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnService;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The two ways a merchant works a return: opening one for an order, and deciding about one.
 *
 * The list and the detail of the administration are plain DAL reads and need no endpoint here - a
 * decision is not: it moves the state machine, may put goods back into the stock and may move money,
 * so it runs through the service and never through a written state id. Opening a return runs through
 * the same service for the same reason: the amount is computed from the order, not from the request.
 *
 * Every endpoint answers with the return in its new shape, so the administration renders the result
 * without reloading the whole row.
 */
#[Package('after-sales')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class OrderReturnController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly OrderReturnService $orderReturnService,
        private readonly OrderReturnPresenter $presenter,
    ) {
    }

    #[Route(
        path: '/api/_action/order-return/{id}/approve',
        name: 'api.action.order-return.approve',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['order_return.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function approve(string $id, Context $context): Response
    {
        return $this->respond($this->orderReturnService->approve($id, $context));
    }

    #[Route(
        path: '/api/_action/order-return/{id}/decline',
        name: 'api.action.order-return.decline',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['order_return.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function decline(string $id, Request $request, Context $context): Response
    {
        $reason = trim((string) $request->request->get('reason', ''));

        return $this->respond(
            $this->orderReturnService->decline($id, $reason, $context, $this->administratorId($context))
        );
    }

    #[Route(
        path: '/api/_action/order-return/{id}/cancel',
        name: 'api.action.order-return.cancel',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['order_return.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function cancel(string $id, Request $request, Context $context): Response
    {
        $note = $request->request->get('note');

        return $this->respond(
            $this->orderReturnService->cancel($id, $context, \is_string($note) ? $note : null)
        );
    }

    #[Route(
        path: '/api/_action/order-return/{id}/receive',
        name: 'api.action.order-return.receive',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['order_return.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function receive(string $id, Context $context): Response
    {
        return $this->respond($this->orderReturnService->receive($id, $context));
    }

    #[Route(
        path: '/api/_action/order-return/{id}/refund',
        name: 'api.action.order-return.refund',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['order_return.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function refund(string $id, Context $context): Response
    {
        return $this->respond($this->orderReturnService->refund($id, $context));
    }

    #[Route(
        path: '/api/_action/order-return/{id}/message',
        name: 'api.action.order-return.message',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['order_return.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function message(string $id, Request $request, Context $context): Response
    {
        $content = trim((string) $request->request->get('content', ''));

        $this->orderReturnService->addMessage(
            $id,
            $content,
            OrderReturnEntity::AUTHOR_AGENT,
            $this->administratorId($context),
            null,
            $context
        );

        return $this->respond($this->orderReturnService->get($id, $context));
    }

    /**
     * What an operator needs to open a return for an order: the positions that are still returnable
     * with the quantity that is left, and the reasons the shop maintains.
     *
     * The page renders its form from this, so a merchant never types an amount and never offers a
     * position the server would reject afterwards - the same contract the storefront uses.
     */
    #[Route(
        path: '/api/_action/order-return/order/{orderId}/positions',
        name: 'api.action.order-return.positions',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['order_return.viewer']],
        methods: [Request::METHOD_GET]
    )]
    public function positions(string $orderId, Context $context): Response
    {
        $order = $this->orderReturnService->getOrder($orderId, $context);

        return new JsonResponse([
            'orderNumber' => $order->getOrderNumber(),
            // the form prints the unit price of a position, so it needs the two facts a price is
            // rendered with - the currency and its decimals
            'currency' => $order->getCurrency()?->getIsoCode(),
            'decimals' => $order->getCurrency()?->getItemRounding()->getDecimals() ?? 2,
            'positions' => $this->orderReturnService->returnablePositions($order, $context),
            'reasons' => $this->orderReturnService->activeReasons($context),
        ]);
    }

    /**
     * Opens a return on behalf of the customer of an order - the phone call case, and the reason the
     * order detail has a returns tab.
     *
     * It is the same creation the customer would run in the storefront, with the same validation: the
     * operator names positions and a type, the server computes the amount and starts the case in
     * `requested`, so the decision about it is still a decision somebody has to take. Only the switch
     * that offers guest returns to the storefront is skipped here (see
     * `OrderReturnService::create()`), because an operator is not a guest.
     */
    #[Route(
        path: '/api/_action/order-return/order/{orderId}',
        name: 'api.action.order-return.create',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['order_return.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function create(string $orderId, Request $request, Context $context): Response
    {
        $order = $this->orderReturnService->getOrder($orderId, $context);
        $orderCustomer = $order->getOrderCustomer();

        $orderReturn = $this->orderReturnService->create(
            [
                'orderId' => $orderId,
                'type' => (string) $request->request->get('type', ''),
                'positions' => $this->positionsOf($request),
                'reasonId' => $this->stringOrNull($request, 'reasonId'),
                'reasonText' => $this->stringOrNull($request, 'reasonText'),
                'customerNote' => $this->stringOrNull($request, 'customerNote'),
                // a guest order has no customer to pass, so the address it was placed with proves the
                // order - exactly the way a guest proves it in the storefront
                'guestEmail' => $orderCustomer?->getEmail(),
            ],
            $context,
            $orderCustomer?->getCustomer(),
            true
        );

        return new JsonResponse(
            ['orderReturn' => $this->presenter->present($orderReturn)],
            Response::HTTP_CREATED
        );
    }

    private function respond(OrderReturnEntity $orderReturn): Response
    {
        return new JsonResponse(['orderReturn' => $this->presenter->present($orderReturn)]);
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

    private function administratorId(Context $context): ?string
    {
        $source = $context->getSource();

        return $source instanceof AdminApiSource ? $source->getUserId() : null;
    }
}

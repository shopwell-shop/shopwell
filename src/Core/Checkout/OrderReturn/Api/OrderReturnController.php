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
 * The decisions a merchant takes on a return.
 *
 * The list and the detail of the administration are plain DAL reads and need no endpoint here - a
 * decision is not: it moves the state machine, may put goods back into the stock and may move money,
 * so it runs through the service and never through a written state id.
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

    private function respond(OrderReturnEntity $orderReturn): Response
    {
        return new JsonResponse(['orderReturn' => $this->presenter->present($orderReturn)]);
    }

    private function administratorId(Context $context): ?string
    {
        $source = $context->getSource();

        return $source instanceof AdminApiSource ? $source->getUserId() : null;
    }
}

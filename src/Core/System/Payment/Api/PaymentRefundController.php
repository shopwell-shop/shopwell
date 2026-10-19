<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Api;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\Payment\Api\Struct\PaymentRefundRequest;
use Shopwell\Core\System\Payment\App\PaymentAppAuthenticator;
use Shopwell\Core\System\Payment\Order\PaymentOrderPresenter;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Refunds of a payment order, partial refunds included.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [PaymentApiRouteScope::ID]])]
class PaymentRefundController extends AbstractPaymentApiController
{
    public function __construct(
        PaymentAppAuthenticator $authenticator,
        private readonly PaymentOrderService $orderService,
        private readonly PaymentRefundService $refundService,
        private readonly PaymentOrderPresenter $presenter,
    ) {
        parent::__construct($authenticator);
    }

    /**
     * A refund is idempotent on the out refund number of the app. It is either settled right away or
     * stays open until the channel confirms it, which the app is told by the state of the refund.
     */
    #[Route(
        path: '/payment-api/v1/payment-orders/{paymentNo}/refunds',
        name: 'payment-api.payment-refund.create',
        methods: [Request::METHOD_POST]
    )]
    public function create(Request $request, string $paymentNo): JsonResponse
    {
        [$app, $params] = $this->authenticate($request);

        $order = $this->orderService->getByPaymentNo($paymentNo, $app);

        $refund = $this->refundService->refund($app, $order, PaymentRefundRequest::fromArray($params));

        return $this->json($this->presenter->refund($this->refundService->getRefund($refund->id)), Response::HTTP_CREATED);
    }
}

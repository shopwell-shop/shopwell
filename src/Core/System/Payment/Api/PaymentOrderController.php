<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Api;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\Payment\Api\Struct\PaymentOrderRequest;
use Shopwell\Core\System\Payment\App\PaymentAppAuthenticator;
use Shopwell\Core\System\Payment\Order\PaymentOrderCapture;
use Shopwell\Core\System\Payment\Order\PaymentOrderCloser;
use Shopwell\Core\System\Payment\Order\PaymentOrderPresenter;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Creates, reads and closes payment orders.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [PaymentApiRouteScope::ID]])]
class PaymentOrderController extends AbstractPaymentApiController
{
    public function __construct(
        PaymentAppAuthenticator $authenticator,
        private readonly PaymentOrderService $orderService,
        private readonly PaymentOrderCapture $captureService,
        private readonly PaymentOrderCloser $closer,
        private readonly PaymentOrderPresenter $presenter,
    ) {
        parent::__construct($authenticator);
    }

    /**
     * Creates the payment order and hands the parameters back the app needs to send the payer to the
     * channel.
     *
     * The call is idempotent on the out trade number of the app: sending it twice returns the very
     * same payment order, and once the order is not payable anymore its state is reported instead of
     * an error - an app may retry after a lost answer.
     */
    #[Route(
        path: '/payment-api/v1/payment-orders',
        name: 'payment-api.payment-order.create',
        methods: [Request::METHOD_POST]
    )]
    public function create(Request $request): JsonResponse
    {
        [$app, $params] = $this->authenticate($request);

        $order = $this->orderService->create($app, PaymentOrderRequest::fromArray($params));

        if (!$this->orderService->isPayable($order)) {
            return $this->json($this->presenter->order($order));
        }

        $result = $this->captureService->capture($app, $order);

        return $this->json($this->presenter->order($this->orderService->getById($order->id), $result));
    }

    #[Route(
        path: '/payment-api/v1/payment-orders/{paymentNo}',
        name: 'payment-api.payment-order.get',
        methods: [Request::METHOD_GET]
    )]
    public function get(Request $request, string $paymentNo): JsonResponse
    {
        [$app] = $this->authenticate($request);

        return $this->json($this->presenter->order($this->orderService->getByPaymentNo($paymentNo, $app)));
    }

    /**
     * Closes a payment order the payer did not pay - and tells the channel to stop accepting it.
     */
    #[Route(
        path: '/payment-api/v1/payment-orders/{paymentNo}/close',
        name: 'payment-api.payment-order.close',
        methods: [Request::METHOD_POST]
    )]
    public function close(Request $request, string $paymentNo): JsonResponse
    {
        [$app] = $this->authenticate($request);

        $order = $this->orderService->getByPaymentNo($paymentNo, $app);
        $this->closer->close($order);

        return $this->json($this->presenter->order($this->orderService->getById($order->id)));
    }
}

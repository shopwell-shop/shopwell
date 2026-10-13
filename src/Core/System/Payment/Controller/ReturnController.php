<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Controller;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Routing\PaymentApiRouteScope;
use Shopwell\Core\System\Payment\Service\PaymentOrderPresenter;
use Shopwell\Core\System\Payment\Service\PaymentOrderService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sends the payer back to the app after the payment.
 *
 * This is a browser request and therefore never authoritative: the state of the payment is decided by
 * the notification of the channel, see {@see \Shopwell\Core\System\Payment\Service\PaymentNotificationService}.
 * The state is handed to the app anyway, so a page can tell the payer what happened without polling.
 *
 * The target is the return url of the payment order, which the app itself registered, so the app always
 * decides where its own payer lands.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [PaymentApiRouteScope::ID]])]
class ReturnController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly PaymentOrderService $orderService,
        private readonly PaymentOrderPresenter $presenter,
    ) {
    }

    #[Route(
        path: '/payment-api/v1/return/{paymentNo}',
        name: 'payment-api.return',
        methods: [Request::METHOD_GET]
    )]
    public function redirectToApp(string $paymentNo): Response
    {
        $order = $this->orderService->findByPaymentNo($paymentNo);

        if (!$order instanceof PaymentOrderEntity) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $target = $this->targetOf($order);

        if ($target === null) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        return new RedirectResponse($this->withState($target, $order), Response::HTTP_FOUND);
    }

    /**
     * The return url of the app, but only when it really is a url a browser can be sent to.
     */
    private function targetOf(PaymentOrderEntity $order): ?string
    {
        $url = $order->returnUrl;

        if ($url === null || $url === '') {
            return null;
        }

        $scheme = parse_url($url, \PHP_URL_SCHEME);

        if (!\in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
            return null;
        }

        return parse_url($url, \PHP_URL_HOST) === null ? null : $url;
    }

    /**
     * Adds the payment numbers and the current state of the payment to the url of the app, keeping
     * whatever query parameters the app put into its own return url.
     */
    private function withState(string $url, PaymentOrderEntity $order): string
    {
        $parameters = [
            'payment_no' => $order->paymentNo,
            'out_trade_no' => $order->outTradeNo,
            'state' => $this->presenter->stateName($order->stateId),
        ];

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . http_build_query($parameters, '', '&', \PHP_QUERY_RFC3986);
    }
}

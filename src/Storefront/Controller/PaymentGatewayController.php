<?php declare(strict_types=1);

namespace Shopwell\Storefront\Controller;

use Shopwell\Core\Checkout\Payment\Gateway\OrderPaymentStarter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\Payment\Exception\PaymentOrderException;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The two browser pages a checkout payment needs after the channel answered: a self-submitting form
 * and a qr code. They render the capture answer again, never capture a second time.
 *
 * @internal
 */
#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class PaymentGatewayController extends StorefrontController
{
    public function __construct(
        private readonly PaymentOrderService $paymentOrders,
        private readonly OrderPaymentStarter $starter,
    ) {
    }

    #[Route(
        path: '/payment/gateway/{orderId}/{paymentNo}/form',
        name: 'frontend.payment.gateway.form',
        methods: ['GET'],
        defaults: [
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID],
            PlatformRequest::ATTRIBUTE_NO_STORE => true,
        ]
    )]
    public function form(string $orderId, string $paymentNo, SalesChannelContext $context): Response
    {
        $order = $this->order($orderId, $paymentNo, $context);
        $params = $this->starter->payParams($order);

        if ($params->flow !== PaymentInteraction::REDIRECT || $params->htmlForm === null) {
            throw PaymentOrderException::orderNotFound($paymentNo);
        }

        // the channel signed this exact document; escaping or rebuilding it would break the payment
        return new Response($params->htmlForm, Response::HTTP_OK, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    #[Route(
        path: '/payment/gateway/{orderId}/{paymentNo}/qr',
        name: 'frontend.payment.gateway.qr',
        methods: ['GET'],
        defaults: [
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID],
            PlatformRequest::ATTRIBUTE_NO_STORE => true,
        ]
    )]
    public function qr(string $orderId, string $paymentNo, SalesChannelContext $context): Response
    {
        $order = $this->order($orderId, $paymentNo, $context);
        $params = $this->starter->payParams($order);
        $qrContent = $params->extra['qr_code'] ?? null;

        if ($params->flow !== PaymentInteraction::QRCODE || !\is_string($qrContent) || $qrContent === '') {
            throw PaymentOrderException::orderNotFound($paymentNo);
        }

        return $this->renderStorefront('@Storefront/storefront/page/payment/gateway/qr.html.twig', [
            'paymentOrder' => $order,
            'qrContent' => $qrContent,
        ]);
    }

    /**
     * The uuid is the unguessable half of the address and the customer check makes the page private;
     * the sequential payment number alone must never be enough to open somebody else's payment.
     *
     * @throws PaymentException
     */
    private function order(string $orderId, string $paymentNo, SalesChannelContext $context): PaymentOrderEntity
    {
        $order = $this->paymentOrders->getById($orderId);
        $customer = $context->getCustomer();

        if (!hash_equals($order->paymentNo, $paymentNo)
            || !$this->paymentOrders->isPayable($order)
            || $customer === null
            || $order->order?->getSalesChannelId() !== $context->getSalesChannelId()
            || $order->order->getOrderCustomer()?->getCustomerId() !== $customer->getId()
        ) {
            throw PaymentOrderException::orderNotFound($paymentNo);
        }

        return $order;
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Storefront\Controller\PaymentGatewayController;
use Shopwell\Tests\Unit\Core\Checkout\Payment\Gateway\PaymentGatewayFixture;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentGatewayController::class)]
class PaymentGatewayControllerTest extends TestCase
{
    public function testTheFormPageReplaysThePersistedChannelAnswer(): void
    {
        $controller = $this->controller(
            ['http_method' => 'POST', 'answer' => '<form id="alipay"></form>'],
            PaymentFlow::REDIRECT
        );

        $response = $controller->form('payment-order-id', 'PG-1', $this->context());

        static::assertSame('<form id="alipay"></form>', $response->getContent());
        static::assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function testTheQrPageRendersThePersistedCodeWithoutCapturingAgain(): void
    {
        $controller = $this->controller(['qr_code' => 'https://pay.example.test/qr'], PaymentFlow::QRCODE);

        $response = $controller->qr('payment-order-id', 'PG-1', $this->context());

        static::assertSame('qr', $response->getContent());
        static::assertSame('https://pay.example.test/qr', $controller->rendered['qrContent'] ?? null);
        static::assertSame('@Storefront/storefront/page/payment/gateway/qr.html.twig', $controller->rendered['view'] ?? null);
    }

    public function testAnotherCustomerCannotOpenTheHandOver(): void
    {
        $controller = $this->controller(['qr_code' => 'https://pay.example.test/qr'], PaymentFlow::QRCODE, 'another-customer');

        $this->expectExceptionObject(PaymentException::orderNotFound('PG-1'));

        $controller->qr('payment-order-id', 'PG-1', $this->context());
    }

    /**
     * @param array<string, mixed> $captureResponse
     *
     * @return PaymentGatewayController&object{rendered: array<string, mixed>}
     */
    private function controller(array $captureResponse, PaymentFlow $flow, string $orderCustomerId = 'customer-id'): PaymentGatewayController
    {
        $checkoutOrder = PaymentGatewayFixture::order();
        $checkoutOrder->assign([
            'salesChannelId' => $this->context()->getSalesChannelId(),
            'orderCustomer' => (new OrderCustomerEntity())->assign(['customerId' => $orderCustomerId]),
        ]);
        $paymentOrder = PaymentGatewayFixture::paymentOrder([
            'order' => $checkoutOrder,
            'transactions' => [PaymentGatewayFixture::captureTransaction($captureResponse)],
        ]);

        $orders = new StaticEntityRepository([new PaymentOrderCollection([$paymentOrder])]);
        $transactions = StaticEntityRepository::of(PaymentTransactionCollection::class);
        $orderService = PaymentGatewayFixture::paymentOrderService($orders, PaymentGatewayFixture::configResolver());
        $starter = PaymentGatewayFixture::starter($orders, $transactions, flow: $flow);

        return new class($orderService, $starter) extends PaymentGatewayController {
            /**
             * @var array<string, mixed>
             */
            public array $rendered = [];

            /**
             * @param array<string, mixed> $parameters
             */
            protected function renderStorefront(string $view, array $parameters = []): Response
            {
                $this->rendered = ['view' => $view, ...$parameters];

                return new Response('qr');
            }
        };
    }

    private function context(): SalesChannelContext
    {
        $context = Generator::generateSalesChannelContext();
        $context->assign([
            'customer' => (new CustomerEntity())->assign(['id' => 'customer-id']),
        ]);

        return $context;
    }
}

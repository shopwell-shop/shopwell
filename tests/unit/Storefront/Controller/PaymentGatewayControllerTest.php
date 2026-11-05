<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentOrderException;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptEntity;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
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
            PaymentInteraction::REDIRECT
        );

        $response = $controller->form('payment-order-id', 'PG-1', $this->context());

        static::assertSame('<form id="alipay"></form>', $response->getContent());
        static::assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function testTheQrPageRendersThePersistedCodeWithoutCapturingAgain(): void
    {
        $controller = $this->controller(['qr_code' => 'https://pay.example.test/qr'], PaymentInteraction::QRCODE);

        $response = $controller->qr('payment-order-id', 'PG-1', $this->context());

        static::assertSame('qr', $response->getContent());
        static::assertSame('https://pay.example.test/qr', $controller->rendered['qrContent'] ?? null);
        static::assertSame('@Storefront/storefront/page/payment/gateway/qr.html.twig', $controller->rendered['view'] ?? null);
    }

    public function testAnotherCustomerCannotOpenTheHandOver(): void
    {
        $controller = $this->controller(['qr_code' => 'https://pay.example.test/qr'], PaymentInteraction::QRCODE, 'another-customer');

        $this->expectExceptionObject(PaymentOrderException::orderNotFound('PG-1'));

        $controller->qr('payment-order-id', 'PG-1', $this->context());
    }

    #[DataProvider('paymentStates')]
    public function testStatusReportsSettlementWithoutStartingAnotherPayment(string $expected, ?string $attemptStatus, ?\DateTimeImmutable $paidAt, ?\DateTimeImmutable $closedAt): void
    {
        $controller = $this->controller([], PaymentInteraction::QRCODE, paymentState: [
            'paidAt' => $paidAt,
            'closedAt' => $closedAt,
        ], attemptStatus: $attemptStatus);

        $response = $controller->status('payment-order-id', 'PG-1', $this->context());

        static::assertSame(['state' => $expected], json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
        static::assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    /**
     * @return iterable<string, array{string, ?string, ?\DateTimeImmutable, ?\DateTimeImmutable}>
     */
    public static function paymentStates(): iterable
    {
        yield 'handover waits for provider confirmation' => ['pending', PaymentAttemptEntity::STATUS_HANDOVER, null, null];
        yield 'unknown attempt remains pending' => ['pending', PaymentAttemptEntity::STATUS_UNKNOWN, null, null];
        yield 'failed attempt allows a new checkout attempt' => ['failed', PaymentAttemptEntity::STATUS_FAILED, null, null];
        yield 'confirmed settlement completes payment' => ['paid', PaymentAttemptEntity::STATUS_PAID, new \DateTimeImmutable(), null];
        yield 'closed obligation stops polling' => ['closed', PaymentAttemptEntity::STATUS_CLOSED, null, new \DateTimeImmutable()];
    }

    public function testAnotherCustomerCannotReadPaymentStatus(): void
    {
        $controller = $this->controller([], PaymentInteraction::QRCODE, 'another-customer');

        $this->expectExceptionObject(PaymentOrderException::orderNotFound('PG-1'));

        $controller->status('payment-order-id', 'PG-1', $this->context());
    }

    public function testAnonymousVisitorCannotReadPaymentStatus(): void
    {
        $controller = $this->controller([], PaymentInteraction::QRCODE);
        $context = $this->context();
        $context->assign(['customer' => null]);

        $this->expectExceptionObject(PaymentOrderException::orderNotFound('PG-1'));

        $controller->status('payment-order-id', 'PG-1', $context);
    }

    public function testAnotherSalesChannelCannotReadPaymentStatus(): void
    {
        $controller = $this->controller([], PaymentInteraction::QRCODE);
        $context = $this->context();
        $context->getSalesChannel()->setId('another-sales-channel');

        $this->expectExceptionObject(PaymentOrderException::orderNotFound('PG-1'));

        $controller->status('payment-order-id', 'PG-1', $context);
    }

    public function testWrongPaymentNumberCannotReadPaymentStatus(): void
    {
        $controller = $this->controller([], PaymentInteraction::QRCODE);

        $this->expectExceptionObject(PaymentOrderException::orderNotFound('wrong-number'));

        $controller->status('payment-order-id', 'wrong-number', $this->context());
    }

    /**
     * @param array<string, mixed> $captureResponse
     * @param array<string, mixed> $paymentState
     *
     * @return PaymentGatewayController&object{rendered: array<string, mixed>}
     */
    private function controller(array $captureResponse, string $flow, string $orderCustomerId = 'customer-id', array $paymentState = [], ?string $attemptStatus = null): PaymentGatewayController
    {
        $checkoutOrder = PaymentGatewayFixture::order();
        $checkoutOrder->assign([
            'salesChannelId' => $this->context()->getSalesChannelId(),
            'orderCustomer' => (new OrderCustomerEntity())->assign(['customerId' => $orderCustomerId]),
        ]);
        $paymentOrder = PaymentGatewayFixture::paymentOrder([
            'order' => $checkoutOrder,
            'transactions' => [PaymentGatewayFixture::captureTransaction($captureResponse)],
            ...$paymentState,
        ]);

        static::assertNotNull($paymentOrder->activeAttempt);
        if ($attemptStatus !== null) {
            $paymentOrder->activeAttempt->status = $attemptStatus;
        }
        $paymentOrder->activeAttempt->handover = [
            'flow' => $flow,
            'http_method' => $captureResponse['http_method'] ?? 'GET',
            'html_form' => $captureResponse['answer'] ?? null,
            'extra' => $flow === PaymentInteraction::QRCODE ? $captureResponse : [],
        ];

        $orders = new StaticEntityRepository([new PaymentOrderCollection([$paymentOrder])]);
        $transactions = StaticEntityRepository::of(PaymentTransactionCollection::class);
        $orderService = PaymentGatewayFixture::paymentOrderService($orders);
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

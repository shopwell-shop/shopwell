<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Payment\Gateway\OrderPaymentStarter;
use Shopwell\Core\Checkout\Payment\PaymentException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentHandover;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentStartResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStarter;
use Shopwell\Core\System\Payment\Order\Struct\PaymentOrderRequest;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Product\PaymentScene;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Routing\PaymentContext;
use Shopwell\Core\System\Payment\Routing\PaymentRoute;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderPaymentStarter::class)]
class OrderPaymentStarterTest extends TestCase
{
    #[DataProvider('currencies')]
    public function testStartsTheResolvedRouteWithCurrencyExponentAndCheckoutReferences(string $currency, float $amount, int $expected): void
    {
        $order = PaymentGatewayFixture::order();
        $order->setCurrency(PaymentGatewayFixture::currency($currency, decimals: 0));
        $transaction = PaymentGatewayFixture::orderTransaction($order);
        $transaction->setAmount(new CalculatedPrice($amount, $amount, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $route = $this->route();
        $paymentOrder = $this->paymentOrder();
        $app = new PaymentAppEntity();
        $orders = $this->createMock(PaymentOrderService::class);
        $orders->expects($this->once())->method('create')->with($app, static::callback(static fn (PaymentOrderRequest $request): bool => $request->amount === $expected && $request->currency === $currency && $request->outTradeNo === $transaction->getId()), $order, $transaction)->willReturn($paymentOrder);
        $starter = $this->createMock(PaymentOrderStarter::class);
        $result = new PaymentStartResult(new PaymentHandover(PaymentInteraction::REDIRECT, 'https://pay.test'));
        $starter->expects($this->once())->method('start')->with($app, $paymentOrder, $route)->willReturn($result);
        $checkout = new OrderPaymentStarter($orders, $starter, static::createStub(RouterInterface::class));

        static::assertSame(['order' => $paymentOrder, 'result' => $result], $checkout->start($order, $transaction, $route, $app, 'https://shop.test/return', $route->context));
    }

    public static function currencies(): iterable
    {
        yield 'yuan in fen despite display precision zero' => ['CNY', 19.99, 1999];
        yield 'yen has no minor exponent' => ['JPY', 1999.0, 1999];
        yield 'dinar has three decimals' => ['BHD', 1.234, 1234];
    }

    public function testRedirectHandoverUsesProviderUrl(): void
    {
        static::assertSame('https://pay.test', $this->checkout()->handOver($this->paymentOrder(), new PaymentStartResult(new PaymentHandover(PaymentInteraction::REDIRECT, 'https://pay.test'))));
    }

    public function testFormAndQrHandoverUseAuthenticatedMiddlePages(): void
    {
        $order = $this->paymentOrder();
        $checkout = $this->checkout();
        static::assertStringContainsString(OrderPaymentStarter::ROUTE_FORM, $checkout->handOver($order, new PaymentStartResult(new PaymentHandover(PaymentInteraction::REDIRECT, htmlForm: '<form></form>'))));
        static::assertStringContainsString(OrderPaymentStarter::ROUTE_QR_CODE, $checkout->handOver($order, new PaymentStartResult(new PaymentHandover(PaymentInteraction::QRCODE))));
    }

    public function testBrowserRefusesUnsupportedInteraction(): void
    {
        $this->expectExceptionObject(PaymentException::channelNotAvailableInStorefront('plugin_product'));
        $this->checkout()->handOver($this->paymentOrder(), new PaymentStartResult(new PaymentHandover('plugin_sdk')));
    }

    public function testMissingRedirectActionIsReported(): void
    {
        $this->expectExceptionObject(PaymentException::gatewayResultMissing('plugin_product'));
        $this->checkout()->handOver($this->paymentOrder(), new PaymentStartResult(new PaymentHandover(PaymentInteraction::REDIRECT)));
    }

    public function testPayParamsReplayTheDurableAttemptWithoutRecapturing(): void
    {
        $order = $this->paymentOrder();
        $params = new PaymentHandover(PaymentInteraction::REDIRECT, 'https://pay.test');
        $starter = $this->createMock(PaymentOrderStarter::class);
        $starter->expects($this->once())->method('replay')->with($order)->willReturn(new PaymentStartResult($params));
        $starter->expects($this->never())->method('start');
        $checkout = new OrderPaymentStarter(static::createStub(PaymentOrderService::class), $starter, static::createStub(RouterInterface::class));

        static::assertSame($params, $checkout->payParams($order));
    }

    private function checkout(): OrderPaymentStarter
    {
        return new OrderPaymentStarter(static::createStub(PaymentOrderService::class), static::createStub(PaymentOrderStarter::class), PaymentGatewayFixture::router());
    }

    private function paymentOrder(): PaymentOrderEntity
    {
        $order = new PaymentOrderEntity();
        $order->id = 'order';
        $order->paymentNo = 'P1';
        $order->activeAttempt = new PaymentAttemptEntity();
        $order->activeAttempt->channelTechnicalName = 'plugin_product';

        return $order;
    }

    private function route(): PaymentRoute
    {
        $product = new PaymentProduct('plugin_product', 'plugin', 'Plugin', [PaymentInteraction::REDIRECT], 'card');
        $provider = new PaymentProvider('plugin', 'Plugin', [], [$product->technicalName => $product], static::createStub(PaymentGatewayFactory::class), static::createStub(PaymentNotificationExtractorInterface::class));

        return new PaymentRoute(new ResolvedPaymentConfig($provider, $product, [], [], [], [], 'test-account'), new PaymentContext('CNY', new PaymentScene('browser', 'wap')), PaymentInteraction::REDIRECT);
    }
}

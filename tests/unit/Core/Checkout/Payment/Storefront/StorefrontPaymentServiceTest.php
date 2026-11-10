<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\Storefront;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\PaymentException;
use Shopwell\Core\Checkout\Payment\Storefront\PlatformPaymentApp;
use Shopwell\Core\Checkout\Payment\Storefront\StorefrontPaymentService;
use Shopwell\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Cashier\PaymentCashierService;
use Shopwell\Core\System\Payment\Cashier\PaymentCashierSessionEntity;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentHandover;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentScene;
use Shopwell\Core\System\Payment\Routing\PaymentContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(StorefrontPaymentService::class)]
class StorefrontPaymentServiceTest extends TestCase
{
    public function testOptionsComeFromTheGenericCashierAndExposeOnlyMethods(): void
    {
        $app = new PaymentAppEntity();
        $platform = static::createStub(PlatformPaymentApp::class);
        $platform->method('app')->willReturn($app);
        $cashier = $this->createMock(PaymentCashierService::class);
        $context = new PaymentContext('CNY', new PaymentScene('browser', 'wap', 'browser'));
        $cashier->expects($this->once())->method('options')->with($app, 'h5', $context)->willReturn([['method' => 'plugin_method', 'translation_key' => 'payment.method.plugin', 'interaction' => 'redirect']]);
        $translator = $this->createMock(AbstractTranslator::class);
        $translator->expects($this->once())->method('trans')->with('payment.method.plugin')->willReturn('Plugin payment');

        $options = (new StorefrontPaymentService($platform, $cashier, $translator))->options($context);

        static::assertSame([['method' => 'plugin_method', 'label' => 'Plugin payment', 'flow' => 'redirect']], $options);
    }

    public function testOptionsUseStorefrontMethodLabelWhenTheProviderKeyIsNotTranslated(): void
    {
        $platform = static::createStub(PlatformPaymentApp::class);
        $platform->method('app')->willReturn(new PaymentAppEntity());
        $cashier = static::createStub(PaymentCashierService::class);
        $cashier->method('options')->willReturn([[
            'method' => 'alipay',
            'translation_key' => 'sw-settings-payment-provider.provider.alipay',
            'interaction' => 'redirect',
        ]]);
        $translator = static::createStub(AbstractTranslator::class);
        $translator->method('trans')->willReturnMap([
            ['sw-settings-payment-provider.provider.alipay', [], null, null, 'sw-settings-payment-provider.provider.alipay'],
            ['payment.method.alipay', [], null, null, 'Alipay'],
        ]);

        $options = (new StorefrontPaymentService($platform, $cashier, $translator))->options(
            new PaymentContext('CNY', new PaymentScene('browser', 'wap', 'browser'))
        );

        static::assertSame('Alipay', $options[0]['label']);
    }

    public function testOpenPreservesPurchaseTermsAndPaysTheSelectedMethod(): void
    {
        $app = new PaymentAppEntity();
        $platform = static::createStub(PlatformPaymentApp::class);
        $platform->method('app')->willReturn($app);
        $cashier = $this->createMock(PaymentCashierService::class);
        $session = new PaymentCashierSessionEntity();
        $session->id = 'session';
        $context = new PaymentContext('JPY', new PaymentScene('browser', 'web', 'browser'));
        $cashier->expects($this->once())->method('create')->with($app, 'h5', ['out_trade_no' => 'purchase', 'amount' => 123, 'subject' => 'Membership', 'attach' => 'purchase:1', 'return_url' => 'https://shop.test/return', 'client_ip' => '127.0.0.1'], $context)->willReturn($session);
        $cashier->expects($this->once())->method('pay')->with($app, 'session', 'plugin_method')->willReturn(['payment_no' => 'P1', 'channel' => 'plugin_product', 'pay' => new PaymentHandover(PaymentInteraction::REDIRECT, 'https://pay.test')]);
        $payments = new StorefrontPaymentService($platform, $cashier, static::createStub(AbstractTranslator::class));

        $result = $payments->open('purchase', 'plugin_method', 123, 'Membership', 'purchase:1', $context, 'https://shop.test/return', '127.0.0.1');

        static::assertSame('plugin_product', $result['channel']);
        static::assertSame('https://pay.test', $result['params']['pay_url']);
    }

    public function testUnavailableMethodIsRejectedBeforeCreatingAPurchase(): void
    {
        $platform = static::createStub(PlatformPaymentApp::class);
        $platform->method('app')->willReturn(new PaymentAppEntity());
        $cashier = $this->createMock(PaymentCashierService::class);
        $cashier->method('options')->willReturn([]);
        $cashier->expects($this->never())->method('create');
        $payments = new StorefrontPaymentService($platform, $cashier, static::createStub(AbstractTranslator::class));

        $this->expectExceptionObject(PaymentException::channelNotAvailableInStorefront('unavailable'));
        $payments->resolveMethod('unavailable', new PaymentContext('CNY', new PaymentScene('browser', 'wap', 'browser')));
    }
}

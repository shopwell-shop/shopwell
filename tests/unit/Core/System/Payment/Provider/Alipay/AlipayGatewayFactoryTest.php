<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Alipay;

use Payum\Core\Gateway;
use Payum\Core\Request\Capture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayApi;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayGatewayFactory;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AlipayGatewayFactory::class)]
class AlipayGatewayFactoryTest extends TestCase
{
    public function testProviderIsAlipay(): void
    {
        static::assertSame('alipay', (new AlipayGatewayFactory())->provider());
    }

    public function testCreateConfigFillsTheDefaults(): void
    {
        $config = (new AlipayGatewayFactory())->createConfig();

        static::assertSame('alipay', $config['payum.factory_name']);
        static::assertSame('alipay', $config['payum.factory_title']);
        static::assertSame('alipay', $config[AbstractPaymentApi::CONFIG_PROVIDER]);
        static::assertSame('', $config[AbstractPaymentApi::CONFIG_CHANNEL]);
        static::assertSame('', $config[AbstractPaymentApi::CONFIG_NOTIFY_URL]);
        static::assertSame('', $config[AbstractPaymentApi::CONFIG_RETURN_URL]);
        static::assertSame([], $config[AbstractPaymentApi::CONFIG_CREDENTIALS]);
        static::assertSame([], $config[AbstractPaymentApi::CONFIG_SETTINGS]);
    }

    public function testCreateConfigKeepsTheGivenValues(): void
    {
        $config = (new AlipayGatewayFactory())->createConfig([
            AbstractPaymentApi::CONFIG_CHANNEL => AlipayGatewayFactory::CHANNEL_QR,
            AbstractPaymentApi::CONFIG_CREDENTIALS => [AlipayApi::APP_ID => '2021000000000000'],
        ]);

        static::assertSame(AlipayGatewayFactory::CHANNEL_QR, $config[AbstractPaymentApi::CONFIG_CHANNEL]);
        static::assertSame([AlipayApi::APP_ID => '2021000000000000'], $config[AbstractPaymentApi::CONFIG_CREDENTIALS]);
        static::assertSame('alipay', $config[AbstractPaymentApi::CONFIG_PROVIDER]);
    }

    public function testCreateBuildsAGateway(): void
    {
        $gateway = (new AlipayGatewayFactory())->create();

        static::assertInstanceOf(Gateway::class, $gateway);
    }

    public function testTheGatewayCarriesTheApiAndTheActionsIntoTheCapture(): void
    {
        $gateway = (new AlipayGatewayFactory())->create([
            AbstractPaymentApi::CONFIG_CHANNEL => 'unknown_channel',
        ]);

        $model = new \ArrayObject([
            PaymentGatewayModel::PAYMENT_NO => 'P-1',
        ]);

        $this->expectExceptionObject(PaymentException::channelNotSupported('unknown_channel', 'alipay'));

        $gateway->execute(new Capture($model));
    }
}

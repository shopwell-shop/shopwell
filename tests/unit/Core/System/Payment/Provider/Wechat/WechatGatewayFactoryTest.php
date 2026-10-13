<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Wechat;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Gateway;
use Payum\Core\Request\Cancel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatGatewayFactory;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WechatGatewayFactory::class)]
class WechatGatewayFactoryTest extends TestCase
{
    public function testProviderReturnsTheTechnicalName(): void
    {
        static::assertSame('wechat', (new WechatGatewayFactory())->provider());
    }

    public function testCreateConfigFillsTheProviderDefaults(): void
    {
        $config = (new WechatGatewayFactory())->createConfig();

        static::assertSame('wechat', $config['payum.factory_name']);
        static::assertSame('wechat', $config['payum.factory_title']);
        static::assertSame('wechat', $config[AbstractPaymentApi::CONFIG_PROVIDER]);
        static::assertSame('', $config[AbstractPaymentApi::CONFIG_CHANNEL]);
        static::assertSame([], $config[AbstractPaymentApi::CONFIG_CREDENTIALS]);
    }

    public function testCreateConfigKeepsTheGivenChannelAndCredentials(): void
    {
        $config = (new WechatGatewayFactory())->createConfig([
            AbstractPaymentApi::CONFIG_CHANNEL => WechatGatewayFactory::CHANNEL_JSAPI,
            AbstractPaymentApi::CONFIG_CREDENTIALS => ['mch_id' => '1900000001'],
        ]);

        static::assertSame(WechatGatewayFactory::CHANNEL_JSAPI, $config[AbstractPaymentApi::CONFIG_CHANNEL]);
        static::assertSame(['mch_id' => '1900000001'], $config[AbstractPaymentApi::CONFIG_CREDENTIALS]);
        static::assertSame('wechat', $config[AbstractPaymentApi::CONFIG_PROVIDER]);
    }

    public function testCreateBuildsAGatewayWithoutCredentials(): void
    {
        $gateway = (new WechatGatewayFactory())->create([
            AbstractPaymentApi::CONFIG_CHANNEL => WechatGatewayFactory::CHANNEL_H5,
            AbstractPaymentApi::CONFIG_PARAMS => [WechatGatewayFactory::PARAM_APP_ID => 'wx-app-id'],
        ]);

        static::assertSame(Gateway::class, $gateway::class);
    }

    public function testTheGatewayRejectsRequestsNoneOfItsActionsSupports(): void
    {
        $gateway = (new WechatGatewayFactory())->create([
            AbstractPaymentApi::CONFIG_CHANNEL => WechatGatewayFactory::CHANNEL_H5,
        ]);

        $this->expectException(RequestNotSupportedException::class);

        $gateway->execute(new Cancel(new \ArrayObject()));
    }
}

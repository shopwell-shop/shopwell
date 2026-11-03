<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigReader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentConfigReader::class)]
class PaymentConfigReaderTest extends TestCase
{
    public function testReadsThePluginConfigShapeWithTranslationsAndPaymentDefaults(): void
    {
        $fields = (new PaymentConfigReader())->fields(__DIR__ . '/_fixtures/payment-provider.xml', 'credentials');

        static::assertSame(['sandbox_mode', 'private_key', 'environment'], array_column($fields, 'name'));
        static::assertSame('0', $fields[0]->default, 'A boolean false keeps the string protocol of the payment form.');
        static::assertSame(['en-GB' => 'Sandbox', 'zh-CN' => '沙箱'], $fields[0]->label);
        static::assertTrue($fields[1]->secret);
        static::assertSame('textarea', $fields[1]->type);
        static::assertSame(['en-GB' => 'Signing key', 'zh-CN' => '签名密钥'], $fields[1]->helpText);
        static::assertSame('select', $fields[2]->type);
        static::assertSame(['live' => ['en-GB' => 'Live', 'zh-CN' => '正式']], $fields[2]->options);
    }

    public function testDifferentChannelCardsMayReuseAFieldName(): void
    {
        $reader = new PaymentConfigReader();
        $file = __DIR__ . '/_fixtures/payment-provider.xml';

        $one = $reader->fields($file, 'channelOne');
        $two = $reader->fields($file, 'channelTwo');

        static::assertSame('shared_name', $one[0]->name);
        static::assertSame('shared_name', $two[0]->name);
        static::assertSame(['en-GB' => 'One'], $one[0]->label);
        static::assertSame(['en-GB' => 'Two'], $two[0]->label);
    }

    public function testChannelCardNamesAreStableAndValidXmlNames(): void
    {
        static::assertSame('channelAlipayH5', PaymentConfigReader::channelCard('alipay_h5'));
        static::assertSame('channelWechatJsapi', PaymentConfigReader::channelCard('wechat_jsapi'));
        static::assertSame('channelMyPay', PaymentConfigReader::channelCard('my-pay'));
    }
}

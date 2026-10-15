<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentConfigField;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentChannel::class)]
class PaymentChannelTest extends TestCase
{
    #[DataProvider('deviceConstants')]
    public function testTheDeviceConstantsAreStable(string $expected, string $actual): void
    {
        static::assertSame($expected, $actual);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function deviceConstants(): \Generator
    {
        yield 'wap' => ['wap', PaymentChannel::DEVICE_WAP];
        yield 'web' => ['web', PaymentChannel::DEVICE_WEB];
        yield 'app' => ['app', PaymentChannel::DEVICE_APP];
        yield 'mini' => ['mini', PaymentChannel::DEVICE_MINI];
    }

    public function testAnEmptyCurrencyListSupportsEveryCurrency(): void
    {
        $channel = $this->createChannel();

        static::assertTrue($channel->supportsCurrency('CNY'));
        static::assertTrue($channel->supportsCurrency('usd'));
    }

    public function testACurrencyListIsMatchedCaseInsensitively(): void
    {
        $channel = $this->createChannel(currencies: ['CNY', 'USD']);

        static::assertTrue($channel->supportsCurrency('cny'));
        static::assertTrue($channel->supportsCurrency('USD'));
        static::assertFalse($channel->supportsCurrency('EUR'));
    }

    public function testAnEmptyDeviceListSupportsEveryDevice(): void
    {
        $channel = $this->createChannel();

        static::assertTrue($channel->supportsDevice('wap'));
        static::assertTrue($channel->supportsDevice('anything'));
    }

    public function testAMissingDeviceAlwaysPasses(): void
    {
        $channel = $this->createChannel(devices: ['wap']);

        static::assertTrue($channel->supportsDevice(null));
        static::assertTrue($channel->supportsDevice(''));
    }

    public function testADeviceListIsMatchedCaseInsensitively(): void
    {
        $channel = $this->createChannel(devices: ['wap', 'web']);

        static::assertTrue($channel->supportsDevice('WAP'));
        static::assertTrue($channel->supportsDevice('web'));
        static::assertFalse($channel->supportsDevice('app'));
    }

    public function testRequiredRuntimeParamsDropsTheOptionalOnes(): void
    {
        $channel = $this->createChannel(runtimeParams: ['openid' => true, 'scene' => false, 'appid' => true]);

        static::assertSame(['openid', 'appid'], $channel->requiredRuntimeParams());
    }

    public function testValidateRuntimeParamsAcceptsAllRequiredValues(): void
    {
        $channel = $this->createChannel(runtimeParams: ['openid' => true, 'scene' => false]);

        static::assertSame(['openid'], $channel->requiredRuntimeParams());

        // the optional parameter is not required, the required one is set: nothing is thrown
        $channel->validateRuntimeParams(['openid' => 'o-1', 'scene' => null]);
    }

    public function testValidateRuntimeParamsRejectsMissingValues(): void
    {
        $channel = $this->createChannel(runtimeParams: ['openid' => true, 'appid' => true]);

        $this->expectExceptionObject(PaymentException::missingRuntimeParams('alipay_h5', ['openid', 'appid']));

        $channel->validateRuntimeParams([]);
    }

    public function testValidateRuntimeParamsRejectsBlankAndNonScalarValues(): void
    {
        $channel = $this->createChannel(runtimeParams: ['openid' => true, 'appid' => true]);

        $this->expectExceptionObject(PaymentException::missingRuntimeParams('alipay_h5', ['openid', 'appid']));

        $channel->validateRuntimeParams(['openid' => '   ', 'appid' => ['not', 'scalar']]);
    }

    public function testToArrayExposesTheDeclaration(): void
    {
        $field = new PaymentConfigField('appid', 'alipay.appid', PaymentConfigField::TYPE_TEXT, required: true);

        $channel = $this->createChannel(
            currencies: ['CNY'],
            devices: ['wap'],
            params: [$field],
            runtimeParams: ['openid' => true],
        );

        static::assertSame([
            'channel' => 'alipay_h5',
            'provider' => 'alipay',
            'label' => 'Alipay H5',
            'flow' => 'redirect',
            'currencies' => ['CNY'],
            'devices' => ['wap'],
            'params' => [$field->toArray()],
            'runtime_params' => ['openid' => true],
        ], $channel->toArray());
    }

    /**
     * @param list<string> $currencies
     * @param list<string> $devices
     * @param list<PaymentConfigField> $params
     * @param array<string, bool> $runtimeParams
     */
    private function createChannel(
        array $currencies = [],
        array $devices = [],
        array $params = [],
        array $runtimeParams = [],
    ): PaymentChannel {
        return new PaymentChannel(
            'alipay_h5',
            'alipay',
            'Alipay H5',
            PaymentFlow::REDIRECT,
            $currencies,
            $devices,
            $params,
            $runtimeParams,
        );
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Struct;

use Payum\Core\GatewayFactoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Struct\ResolvedPaymentConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ResolvedPaymentConfig::class)]
class ResolvedPaymentConfigTest extends TestCase
{
    public function testTheResolvedValuesAreKept(): void
    {
        $provider = $this->provider('alipay');
        $channel = $this->channel('alipay_h5', 'alipay');

        $config = new ResolvedPaymentConfig(
            provider: $provider,
            channel: $channel,
            credentials: ['app_id' => 'platform-app', 'private_key' => 'secret'],
            params: ['appid' => 'wx-appid'],
            runtimeParams: ['openid' => 'o-1'],
            settings: ['sandbox' => '1'],
            mode: 'platform',
        );

        static::assertSame($provider, $config->provider);
        static::assertSame($channel, $config->channel);
        static::assertSame(['app_id' => 'platform-app', 'private_key' => 'secret'], $config->credentials);
        static::assertSame(['appid' => 'wx-appid'], $config->params);
        static::assertSame(['openid' => 'o-1'], $config->runtimeParams);
        static::assertSame(['sandbox' => '1'], $config->settings);
        static::assertSame('platform', $config->mode);
    }

    public function testGetReadsACredentialWithAFallback(): void
    {
        $config = $this->config(['app_id' => 'platform-app'], []);

        static::assertSame('platform-app', $config->get('app_id'));
        static::assertSame('fallback', $config->get('missing', 'fallback'));
        static::assertNull($config->get('missing'));
    }

    public function testGetFallsBackForNonScalarOrDefaultValues(): void
    {
        $config = $this->config(['nested' => ['a' => 1], 'blank' => '', 'number' => 42], []);

        static::assertSame('x', $config->get('nested', 'x'));
        static::assertSame('x', $config->get('blank', 'x'));
        static::assertSame('42', $config->get('number'));
    }

    public function testRequireReturnsTheCredential(): void
    {
        $config = $this->config(['app_id' => 'platform-app'], []);

        static::assertSame('platform-app', $config->require('app_id'));
    }

    public function testRequireThrowsWhenTheCredentialIsMissing(): void
    {
        $config = $this->config([], []);

        $this->expectExceptionObject(PaymentException::missingCredentials('alipay', 'app_id'));

        $config->require('app_id');
    }

    public function testParamReadsAChannelParameterWithAFallback(): void
    {
        $config = $this->config([], ['appid' => 'wx-appid']);

        static::assertSame('wx-appid', $config->param('appid'));
        static::assertSame('fallback', $config->param('missing', 'fallback'));
        static::assertNull($config->param('missing'));
    }

    public function testRequireParamReturnsTheParameter(): void
    {
        $config = $this->config([], ['appid' => 'wx-appid']);

        static::assertSame('wx-appid', $config->requireParam('appid'));
    }

    public function testRequireParamThrowsWhenTheParameterIsMissing(): void
    {
        $config = $this->config([], []);

        $this->expectExceptionObject(PaymentException::channelNotConfigured('alipay_h5', 'appid'));

        $config->requireParam('appid');
    }

    public function testRuntimeParamAndSettingReadTheirOwnBag(): void
    {
        $provider = $this->provider('alipay');
        $channel = $this->channel('alipay_h5', 'alipay');

        $config = new ResolvedPaymentConfig(
            provider: $provider,
            channel: $channel,
            credentials: ['app_id' => 'platform-app'],
            params: ['appid' => 'wx-appid'],
            runtimeParams: ['openid' => 'o-1'],
            settings: ['sandbox' => '1'],
            mode: 'own',
        );

        static::assertSame('o-1', $config->runtimeParam('openid'));
        static::assertNull($config->runtimeParam('missing'));
        static::assertSame('fallback', $config->runtimeParam('missing', 'fallback'));

        static::assertSame('1', $config->setting('sandbox'));
        static::assertNull($config->setting('missing'));
        static::assertSame('fallback', $config->setting('missing', 'fallback'));
    }

    /**
     * @param array<string, mixed> $credentials
     * @param array<string, mixed> $params
     */
    private function config(array $credentials, array $params): ResolvedPaymentConfig
    {
        return new ResolvedPaymentConfig(
            provider: $this->provider('alipay'),
            channel: $this->channel('alipay_h5', 'alipay'),
            credentials: $credentials,
            params: $params,
            runtimeParams: [],
            settings: [],
            mode: 'platform',
        );
    }

    private function provider(string $technicalName): PaymentProvider
    {
        return new PaymentProvider(
            $technicalName,
            $technicalName,
            [],
            [],
            static::createStub(GatewayFactoryInterface::class),
            static::createStub(PaymentNotificationExtractorInterface::class),
        );
    }

    private function channel(string $technicalName, string $providerName): PaymentChannel
    {
        return new PaymentChannel($technicalName, $providerName, $technicalName, PaymentFlow::REDIRECT);
    }
}

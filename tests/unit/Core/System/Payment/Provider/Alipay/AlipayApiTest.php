<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Alipay;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\CloseResult;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\QueryResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayApi;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AlipayApi::class)]
class AlipayApiTest extends TestCase
{
    public function testExposesTheConfigurationOfThePayment(): void
    {
        $api = $this->api(credentials: [AlipayApi::APP_ID => '2021000000000000']);

        static::assertSame('alipay', $api->provider());
        static::assertSame('alipay_h5', $api->channel());
        static::assertNull($api->sourceAppId());
        static::assertFalse($api->inherited());
        static::assertSame([AlipayApi::APP_ID => '2021000000000000'], $api->credentials());
        static::assertSame('https://example.com/notify', $api->notifyUrl());
        static::assertSame('https://example.com/return', $api->returnUrl());
    }

    public function testGatewayUrlFallsBackToTheProductionGateway(): void
    {
        $api = $this->api();

        static::assertSame(AlipayApi::DEFAULT_GATEWAY_URL, $api->gatewayUrl());
    }

    public function testGatewayUrlUsesAndTrimsTheConfiguredGateway(): void
    {
        $api = $this->api(credentials: [AlipayApi::GATEWAY_URL => 'https://alipay.example.com/']);

        static::assertSame('https://alipay.example.com', $api->gatewayUrl());
    }

    public function testGatewayUrlUsesTheSandboxGatewayWhenTheSandboxIsOn(): void
    {
        $api = $this->api(credentials: [
            AlipayApi::SANDBOX => '1',
            AlipayApi::GATEWAY_URL => 'https://alipay.example.com',
        ]);

        static::assertTrue($api->isSandbox());
        static::assertSame(AlipayApi::SANDBOX_GATEWAY_URL, $api->gatewayUrl());
    }

    public function testSandboxIsOffWhenTheCredentialIsMissingEmptyOrZero(): void
    {
        static::assertFalse($this->api()->isSandbox());
        static::assertFalse($this->api(credentials: [AlipayApi::SANDBOX => ''])->isSandbox());
        static::assertFalse($this->api(credentials: [AlipayApi::SANDBOX => '0'])->isSandbox());
        static::assertTrue($this->api(credentials: [AlipayApi::SANDBOX => 'yes'])->isSandbox());
    }

    public function testSignTypeDefaultsToRsa2(): void
    {
        $api = $this->api();

        static::assertSame(AlipayApi::DEFAULT_SIGN_TYPE, $api->signType());
    }

    public function testSignTypeIsUppercased(): void
    {
        $api = $this->api(credentials: [AlipayApi::SIGN_TYPE => 'rsa']);

        static::assertSame('RSA', $api->signType());
    }

    public function testPublicKeyStripsThePemEnvelope(): void
    {
        $api = $this->api(credentials: [
            AlipayApi::PUBLIC_KEY => "-----BEGIN PUBLIC KEY-----\nABCDEFG\n-----END PUBLIC KEY-----",
        ]);

        static::assertSame('ABCDEFG', $api->publicKey());
    }

    public function testPublicKeyThrowsWhenTheCredentialIsMissing(): void
    {
        $api = $this->api();

        $this->expectExceptionObject(PaymentException::missingCredentials('alipay', AlipayApi::PUBLIC_KEY));

        $api->publicKey();
    }

    public function testAmountConvertsTheMinorUnitToTheDecimalStringOfTheCurrency(): void
    {
        $api = $this->api();

        static::assertSame('12.34', $api->amount(1234, 'CNY'));
        static::assertSame('1234', $api->amount(1234, 'JPY'));
    }

    public function testConfigUtilBuildsAndCachesTheSdkConfiguration(): void
    {
        $api = $this->api(credentials: [
            AlipayApi::APP_ID => '2021000000000000',
            AlipayApi::PRIVATE_KEY => 'RAW-PRIVATE-KEY',
            AlipayApi::PUBLIC_KEY => "-----BEGIN PUBLIC KEY-----\nABCDEFG\n-----END PUBLIC KEY-----",
            AlipayApi::SIGN_TYPE => 'rsa',
            AlipayApi::GATEWAY_URL => 'https://alipay.example.com/',
        ]);

        $configUtil = $api->configUtil();

        static::assertSame($configUtil, $api->configUtil());
        static::assertSame('2021000000000000', $configUtil->getAppId());
        static::assertSame("-----BEGIN RSA PRIVATE KEY-----\nRAW-PRIVATE-KEY\n-----END RSA PRIVATE KEY-----", $configUtil->getPrivateKey());
        static::assertSame('ABCDEFG', $configUtil->getAlipayPublicKey());
        static::assertSame('RSA', $configUtil->getSignType());
        static::assertSame('https://alipay.example.com', $configUtil->getServerUrl());
        static::assertSame('json', $configUtil->getFormat());
        static::assertSame('UTF-8', $configUtil->getCharset());
    }

    public function testConfigUtilKeepsAnAlreadyWrappedPrivateKey(): void
    {
        $api = $this->api(credentials: [
            AlipayApi::APP_ID => '2021000000000000',
            AlipayApi::PRIVATE_KEY => "-----BEGIN PRIVATE KEY-----\nWRAPPED\n-----END PRIVATE KEY-----",
            AlipayApi::PUBLIC_KEY => 'ABCDEFG',
        ]);

        static::assertSame(
            "-----BEGIN PRIVATE KEY-----\nWRAPPED\n-----END PRIVATE KEY-----",
            $api->configUtil()->getPrivateKey()
        );
    }

    public function testConfigurationUsesAndCachesTheGatewayUrl(): void
    {
        $api = $this->api(credentials: [AlipayApi::GATEWAY_URL => 'https://alipay.example.com']);

        $configuration = $api->configuration();

        static::assertSame($configuration, $api->configuration());
        static::assertSame('https://alipay.example.com', $configuration->getHost());
    }

    public function testTheSdkApiObjectsAreBuiltOnce(): void
    {
        $api = $this->api(credentials: [
            AlipayApi::APP_ID => '2021000000000000',
            AlipayApi::PRIVATE_KEY => 'RAW-PRIVATE-KEY',
            AlipayApi::PUBLIC_KEY => 'ABCDEFG',
        ]);

        static::assertSame($api->tradeApi(), $api->tradeApi());
        static::assertSame($api->genericApi(), $api->genericApi());
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function closeErrorCodes(): iterable
    {
        yield 'ACQ.TRADE_NOT_EXIST' => ['ACQ.TRADE_NOT_EXIST', CloseResult::STATUS_NOT_FOUND];
        // the legacy gateway calls the same thing without the prefix
        yield 'TRADE_NOT_EXIST' => ['TRADE_NOT_EXIST', CloseResult::STATUS_NOT_FOUND];
        // "paid" and "closed" share this code - it is decided by asking the trade, not by the code
        yield 'ACQ.TRADE_STATUS_ERROR' => ['ACQ.TRADE_STATUS_ERROR', null];
        yield 'ACQ.SYSTEM_ERROR' => ['ACQ.SYSTEM_ERROR', null];
    }

    #[DataProvider('closeErrorCodes')]
    public function testTheCloseErrorCodesThatAreDecidedWithoutAskingTheTrade(string $code, ?string $expected): void
    {
        $result = AlipayApi::closeResultForError($code, ['code' => $code]);

        if ($expected === null) {
            static::assertNull($result);

            return;
        }

        static::assertInstanceOf(CloseResult::class, $result);
        static::assertSame($expected, $result->status);
        static::assertSame($code, $result->channelStatus);
        static::assertSame(['code' => $code], $result->raw);
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function closeTradeStatuses(): iterable
    {
        // the money is there: closing the order locally would lose it
        yield 'paid' => [QueryResult::STATUS_PAID, CloseResult::STATUS_ALREADY_PAID];
        // already closed is what the caller wanted
        yield 'closed' => [QueryResult::STATUS_CLOSED, CloseResult::STATUS_CLOSED];
        yield 'pending' => [QueryResult::STATUS_PENDING, null];
        yield 'absent' => [QueryResult::STATUS_ABSENT, null];
        yield 'failed' => [QueryResult::STATUS_FAILED, null];
    }

    #[DataProvider('closeTradeStatuses')]
    public function testTheCloseAnswerOfATradeThatCannotBeClosed(string $tradeStatus, ?string $expected): void
    {
        $result = AlipayApi::closeResultForTrade($tradeStatus);

        if ($expected === null) {
            static::assertNull($result);

            return;
        }

        static::assertInstanceOf(CloseResult::class, $result);
        static::assertSame($expected, $result->status);
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function api(array $credentials = []): AlipayApi
    {
        $channel = new PaymentChannel('alipay_h5', 'alipay', 'alipay_h5', PaymentFlow::REDIRECT);

        return new AlipayApi(
            new ResolvedPaymentConfig(
                provider: new PaymentProvider(
                    'alipay',
                    'alipay',
                    [],
                    ['alipay_h5' => $channel],
                    new PaymentGatewayFactory('alipay', AlipayApi::class, []),
                    static::createStub(PaymentNotificationExtractorInterface::class),
                ),
                channel: $channel,
                credentials: $credentials,
                params: [],
                runtimeParams: [],
                settings: [],
            ),
            'https://example.com/notify',
            'https://example.com/return',
        );
    }
}

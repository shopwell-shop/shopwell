<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Integration\Alipay\Api;

use Alipay\OpenAPISDK\Api\AlipayTradeApi;
use Alipay\OpenAPISDK\Model\AlipayTradeCreateDefaultResponse;
use Alipay\OpenAPISDK\Model\AlipayTradeCreateResponseModel;
use Alipay\OpenAPISDK\Model\AlipayTradePrecreateModel;
use Alipay\OpenAPISDK\Model\AlipayTradePrecreateResponseModel;
use Alipay\OpenAPISDK\Model\AlipayTradeRefundModel;
use Alipay\OpenAPISDK\Model\AlipayTradeRefundResponseModel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\Request\CloseResult;
use Shopwell\Core\System\Payment\Gateway\Request\QueryResult;
use Shopwell\Core\System\Payment\Integration\Alipay\Api\AlipayApi;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
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
        static::assertSame("-----BEGIN PUBLIC KEY-----\nABCDEFG\n-----END PUBLIC KEY-----", $configUtil->getAlipayPublicKey());
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

    public function testConfigUtilWrapsABareAlipayPublicKey(): void
    {
        $api = $this->api(credentials: [
            AlipayApi::APP_ID => '2021000000000000',
            AlipayApi::PRIVATE_KEY => 'RAW-PRIVATE-KEY',
            AlipayApi::PUBLIC_KEY => 'ABCDEFG',
        ]);

        // the response verification of the sdk hands the key straight to openssl_verify(), which
        // only coerces a pem string - a bare base64 body is the "cannot be coerced" failure
        static::assertSame(
            "-----BEGIN PUBLIC KEY-----\nABCDEFG\n-----END PUBLIC KEY-----",
            $api->configUtil()->getAlipayPublicKey()
        );
    }

    public function testConfigUtilKeepsAnAlreadyWrappedAlipayPublicKey(): void
    {
        $wrapped = "-----BEGIN PUBLIC KEY-----\nWRAPPED-KEY\n-----END PUBLIC KEY-----";

        $api = $this->api(credentials: [
            AlipayApi::APP_ID => '2021000000000000',
            AlipayApi::PRIVATE_KEY => 'RAW-PRIVATE-KEY',
            AlipayApi::PUBLIC_KEY => $wrapped,
        ]);

        static::assertSame($wrapped, $api->configUtil()->getAlipayPublicKey());
    }

    public function testTheRefundCallCarriesTheOrderTheRefundNumberAndTheReason(): void
    {
        $refund = null;

        $tradeApi = $this->createMock(AlipayTradeApi::class);
        $tradeApi->expects($this->once())
            ->method('refund')
            ->willReturnCallback(static function (AlipayTradeRefundModel $model) use (&$refund): AlipayTradeRefundResponseModel {
                $refund = $model;

                return (new AlipayTradeRefundResponseModel())->setTradeNo('T-1')->setFundChange('Y');
            });

        $answer = $this->api(tradeApi: $tradeApi)->refundPayment([
            'out_trade_no' => 'P-1',
            'refund_amount' => '12.34',
            'out_request_no' => 'R-1',
            'refund_reason' => 'damaged',
        ]);

        static::assertInstanceOf(AlipayTradeRefundModel::class, $refund);
        static::assertSame('P-1', $refund->getOutTradeNo());
        static::assertSame('12.34', $refund->getRefundAmount());
        static::assertSame('R-1', $refund->getOutRequestNo());
        static::assertSame('damaged', $refund->getRefundReason());
        static::assertSame(['trade_no' => 'T-1', 'fund_change' => 'Y'], $answer);
    }

    public function testTheQrCodeOfAPrecreatedTradeIsTheAnswer(): void
    {
        $trade = null;

        $tradeApi = $this->createMock(AlipayTradeApi::class);
        $tradeApi->expects($this->once())
            ->method('precreate')
            ->willReturnCallback(static function (AlipayTradePrecreateModel $model) use (&$trade): AlipayTradePrecreateResponseModel {
                $trade = $model;

                return (new AlipayTradePrecreateResponseModel())->setQrCode('https://qr.alipay.com/abc');
            });

        $answer = $this->api(tradeApi: $tradeApi)->precreatePayment([
            'out_trade_no' => 'P-1',
            'total_amount' => '12.34',
            'subject' => 'Order subject',
        ]);

        static::assertInstanceOf(AlipayTradePrecreateModel::class, $trade);
        static::assertSame('P-1', $trade->getOutTradeNo());
        static::assertSame('12.34', $trade->getTotalAmount());
        static::assertSame('Order subject', $trade->getSubject());
        // the notification of a qr payment arrives at the platform, not at the payer
        static::assertSame('https://example.com/notify', $trade->getNotifyUrl());
        static::assertSame(['qr_code' => 'https://qr.alipay.com/abc'], $answer);
    }

    public function testTheTradeNumberOfACreatedTradeIsTheAnswer(): void
    {
        $tradeApi = $this->createMock(AlipayTradeApi::class);
        $tradeApi->expects($this->once())
            ->method('create')
            ->willReturn((new AlipayTradeCreateResponseModel())->setTradeNo('T-1'));

        $answer = $this->api(tradeApi: $tradeApi)->createPayment([
            'out_trade_no' => 'P-1',
            'total_amount' => '12.34',
            'buyer_id' => '2088102147948060',
        ]);

        static::assertSame(['trade_no' => 'T-1'], $answer);
    }

    public function testARejectedCallIsAnEmptyAnswer(): void
    {
        $tradeApi = $this->createMock(AlipayTradeApi::class);
        $tradeApi->expects($this->once())
            ->method('create')
            // alipay answers its default response when it rejects the call, which carries no trade
            ->willReturn(new AlipayTradeCreateDefaultResponse());

        static::assertSame([], $this->api(tradeApi: $tradeApi)->createPayment(['out_trade_no' => 'P-1']));
    }

    public function testANotificationIsVerifiedWithTheBareAndWithTheEnvelopedPublicKey(): void
    {
        $keys = self::keyPair();
        $parameters = self::signedParameters($keys['private'], [
            'out_trade_no' => 'P-1',
            'total_amount' => '12.34',
            'trade_status' => 'TRADE_SUCCESS',
            'sign_type' => 'RSA2',
        ]);

        // the signature util wraps a bare base64 body by itself ...
        $bare = $this->api([AlipayApi::PUBLIC_KEY => AlipayApi::stripPem($keys['public'])]);
        static::assertTrue($bare->verifyNotificationSignature($parameters));

        // ... and a key that is configured with its pem envelope is stripped before it is handed over
        $enveloped = $this->api([AlipayApi::PUBLIC_KEY => $keys['public']]);
        static::assertTrue($enveloped->verifyNotificationSignature($parameters));
    }

    public function testANotificationWithoutASignatureIsNotVerified(): void
    {
        $api = $this->api([AlipayApi::PUBLIC_KEY => self::keyPair()['public']]);

        static::assertFalse($api->verifyNotificationSignature(['out_trade_no' => 'P-1']));
    }

    public function testATamperedNotificationIsNotVerified(): void
    {
        $keys = self::keyPair();
        $parameters = self::signedParameters($keys['private'], ['out_trade_no' => 'P-1', 'total_amount' => '12.34']);

        $parameters['total_amount'] = '99.99';

        static::assertFalse($this->api([AlipayApi::PUBLIC_KEY => $keys['public']])->verifyNotificationSignature($parameters));
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
    private function api(array $credentials = [], ?AlipayTradeApi $tradeApi = null): AlipayApi
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
            $tradeApi,
        );
    }

    /**
     * @return array{private: string, public: string}
     */
    private static function keyPair(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        static::assertNotFalse($key);

        $privateKey = '';
        openssl_pkey_export($key, $privateKey);

        $details = openssl_pkey_get_details($key);
        static::assertIsArray($details);

        return ['private' => $privateKey, 'public' => $details['key']];
    }

    /**
     * The parameters of a notification as alipay sends them: signed over the sorted fields, the sign
     * type excluded.
     *
     * @param array<string, string> $parameters
     *
     * @return array<string, string>
     */
    private static function signedParameters(string $privateKey, array $parameters): array
    {
        $signed = $parameters;
        unset($signed['sign_type']);

        ksort($signed);

        $content = implode('&', array_map(
            static fn (string $key): string => $key . '=' . $signed[$key],
            array_keys($signed)
        ));

        static::assertTrue(openssl_sign($content, $signature, $privateKey, \OPENSSL_ALGO_SHA256));

        $parameters['sign'] = base64_encode($signature);

        return $parameters;
    }
}

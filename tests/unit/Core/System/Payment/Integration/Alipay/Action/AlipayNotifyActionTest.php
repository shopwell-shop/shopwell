<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Integration\Alipay\Action;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Notify;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\NotificationResult;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayNotifyAction;
use Shopwell\Core\System\Payment\Integration\Alipay\Api\AlipayApi;
use Shopwell\Core\System\Payment\Integration\Alipay\Product\AlipayProducts;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AlipayNotifyAction::class)]
class AlipayNotifyActionTest extends TestCase
{
    /**
     * @var array{private: string, public: string}|null
     */
    private static ?array $keys = null;

    public function testSupportsOnlyNotifyRequestsWithAPaymentGatewayModel(): void
    {
        $action = new AlipayNotifyAction();

        static::assertTrue($action->supports(new Notify(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Capture(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Notify(new \stdClass())));
    }

    public function testExecuteRejectsARequestItDoesNotSupport(): void
    {
        $action = new AlipayNotifyAction();

        $this->expectException(RequestNotSupportedException::class);

        $action->execute(new Capture(new PaymentGatewayModel()));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new AlipayNotifyAction();

        $this->expectExceptionObject(PaymentGatewayException::actionWithoutApi(AlipayNotifyAction::class));

        $action->execute(new Notify(new PaymentGatewayModel()));
    }

    public function testSetApiRejectsAnApiItDoesNotWorkWith(): void
    {
        $action = new AlipayNotifyAction();

        $this->expectExceptionObject(PaymentGatewayException::unsupportedApi(AlipayNotifyAction::class, \stdClass::class));

        $action->setApi(new \stdClass());
    }

    public function testExecuteReadsASucceededTrade(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->apiWithValidSignature());

        $model = $this->model($this->signedBody([
            'out_trade_no' => 'P-1',
            'trade_no' => '2024010100001',
            'trade_status' => 'TRADE_SUCCESS',
            'total_amount' => '12.34',
            'currency' => 'cny',
        ]));

        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, AlipayProducts::H5);

        static::assertTrue($result->signatureValid);
        static::assertTrue($result->succeeded);
        static::assertSame('2024010100001', $result->outPaymentNo);
        static::assertSame(1234, $result->amount);
        static::assertSame('CNY', $result->currency);
        static::assertSame('TRADE_SUCCESS', $result->status);
        static::assertSame('2024010100001', $result->raw['trade_no']);
    }

    public function testExecuteTreatsAFinishedTradeAsSucceeded(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->apiWithValidSignature());

        $model = $this->model($this->signedBody(['trade_status' => 'TRADE_FINISHED']));

        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, AlipayProducts::H5);

        static::assertTrue($result->succeeded);
        static::assertSame('TRADE_FINISHED', $result->status);
    }

    /**
     * A body that claims TRADE_SUCCESS without proving it comes from alipay is not a success - only a
     * verified notification may report one.
     */
    public function testExecuteDoesNotSucceedOnAClaimTheSignatureDoesNotProve(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->api());

        $model = $this->model('trade_status=TRADE_SUCCESS&total_amount=12.34');

        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, AlipayProducts::H5);

        static::assertFalse($result->signatureValid);
        static::assertFalse($result->succeeded);
    }

    public function testExecuteMarksATradeThatDidNotSucceedAsNotSucceeded(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->api());

        $model = $this->model('trade_status=WAIT_BUYER_PAY');

        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, AlipayProducts::H5);

        static::assertFalse($result->succeeded);
        static::assertSame('WAIT_BUYER_PAY', $result->status);
    }

    public function testExecuteUppercasesTheCurrencyAndConvertsTheAmount(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->api());

        $model = $this->model('trade_status=TRADE_SUCCESS&total_amount=1.5&currency=usd');

        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, AlipayProducts::H5);

        static::assertSame('USD', $result->currency);
        static::assertSame(150, $result->amount);
    }

    public function testExecuteDefaultsToTheCnyCurrency(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->api());

        $model = $this->model('trade_status=TRADE_SUCCESS&total_amount=5');

        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, AlipayProducts::H5);

        static::assertSame(AlipayNotifyAction::DEFAULT_CURRENCY, $result->currency);
        static::assertSame(500, $result->amount);
    }

    public function testExecuteIgnoresAnAmountThatIsNotANumber(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->api());

        $model = $this->model('trade_status=TRADE_SUCCESS&total_amount=not-a-number');

        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, AlipayProducts::H5);

        static::assertNull($result->amount);
    }

    public function testExecuteWithoutATradeNumberReportsNoOutPaymentNo(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->api());

        $model = $this->model('trade_status=TRADE_SUCCESS');

        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, AlipayProducts::H5);

        static::assertNull($result->outPaymentNo);
        static::assertNull($result->amount);
    }

    public function testExecuteMarksASignatureAsInvalidWhenThePublicKeyIsMissing(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->api());

        $model = $this->model('trade_status=TRADE_SUCCESS&sign=whatever&sign_type=RSA2');

        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, AlipayProducts::H5);

        static::assertFalse($result->signatureValid);
        static::assertFalse($result->succeeded);
    }

    public function testExecuteRecordsTheParsedBodyAsResponse(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->api());

        $model = $this->model('out_trade_no=P-1&trade_status=TRADE_SUCCESS');

        $action->execute(new Notify($model));

        $response = $model->response();
        static::assertSame('P-1', $response['out_trade_no']);
        static::assertSame('TRADE_SUCCESS', $response['trade_status']);
    }

    /**
     * @return array{private: string, public: string}
     */
    private static function keys(): array
    {
        if (self::$keys !== null) {
            return self::$keys;
        }

        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        static::assertNotFalse($resource);

        openssl_pkey_export($resource, $privateKey);
        $details = openssl_pkey_get_details($resource);
        static::assertIsArray($details);

        return self::$keys = ['private' => (string) $privateKey, 'public' => (string) $details['key']];
    }

    private function apiWithValidSignature(): AlipayApi
    {
        return $this->api([AlipayApi::PUBLIC_KEY => self::keys()['public']]);
    }

    /**
     * A notification whose signature really verifies, so a test reads the mapping of a success and not
     * the mapping of a forged body. Signed over the sorted fields, the sign type excluded - exactly
     * what alipay's rsaCheckV1 expects.
     *
     * @param array<string, string> $parameters
     */
    private function signedBody(array $parameters): string
    {
        $keys = self::keys();

        $signed = $parameters;
        unset($signed['sign'], $signed['sign_type']);
        ksort($signed);

        $content = implode('&', array_map(
            static fn (string $key): string => $key . '=' . $signed[$key],
            array_keys($signed)
        ));

        static::assertTrue(openssl_sign($content, $signature, $keys['private'], \OPENSSL_ALGO_SHA256));

        $parameters['sign'] = base64_encode($signature);

        return http_build_query($parameters);
    }

    private function model(string $rawBody): PaymentGatewayModel
    {
        return new PaymentGatewayModel([
            PaymentGatewayModel::RAW_BODY => $rawBody,
        ]);
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function api(array $credentials = []): AlipayApi
    {
        $channel = new PaymentProduct(AlipayProducts::H5, 'alipay', AlipayProducts::H5, [PaymentInteraction::REDIRECT], 'alipay');

        return new AlipayApi(
            new ResolvedPaymentConfig(
                provider: new PaymentProvider(
                    'alipay',
                    'alipay',
                    [],
                    [AlipayProducts::H5 => $channel],
                    new PaymentGatewayFactory('alipay', AlipayApi::class, []),
                    static::createStub(PaymentNotificationExtractorInterface::class),
                ),
                product: $channel,
                credentials: $credentials,
                params: [],
                runtimeParams: [],
                settings: [],
                accountId: 'test-account',
            ),
            'https://example.com/notify',
            '',
        );
    }
}

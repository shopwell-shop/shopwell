<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Integration\Stripe\Action;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Refund;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentHandover;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentStartResult;
use Shopwell\Core\System\Payment\Integration\Stripe\Action\StripeCaptureAction;
use Shopwell\Core\System\Payment\Integration\Stripe\Api\StripeApi;
use Shopwell\Core\System\Payment\Integration\Stripe\Notification\StripeNotificationExtractor;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Tests\Unit\Core\System\Payment\Integration\Stripe\FakeStripeHttpClient;
use Stripe\ApiRequestor;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StripeCaptureAction::class)]
class StripeCaptureActionTest extends TestCase
{
    private const SESSION_PATH = '/v1/checkout/sessions';

    private const INTENT_PATH = '/v1/payment_intents';

    private FakeStripeHttpClient $http;

    protected function setUp(): void
    {
        // the sdk of stripe talks through a global http client - the fake below intercepts every
        // request before it would leave the process, and records it for the assertions
        $this->http = new FakeStripeHttpClient();
        ApiRequestor::setHttpClient($this->http);
    }

    public function testSupportsOnlyCaptureRequestsWithAPaymentGatewayModel(): void
    {
        $action = new StripeCaptureAction();

        static::assertTrue($action->supports(new Capture(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Refund(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Capture(new \stdClass())));
    }

    public function testExecuteRejectsARequestItDoesNotSupport(): void
    {
        $action = new StripeCaptureAction();

        $this->expectException(RequestNotSupportedException::class);

        $action->execute(new Refund(new PaymentGatewayModel()));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new StripeCaptureAction();

        $this->expectExceptionObject(PaymentGatewayException::actionWithoutApi(StripeCaptureAction::class));

        $action->execute(new Capture(new PaymentGatewayModel()));
    }

    public function testSetApiRejectsAnApiItDoesNotWorkWith(): void
    {
        $action = new StripeCaptureAction();

        $this->expectExceptionObject(PaymentGatewayException::unsupportedApi(StripeCaptureAction::class, \stdClass::class));

        $action->setApi(new \stdClass());
    }

    public function testTheDefaultFlowIsTheHostedCheckoutRedirect(): void
    {
        $action = new StripeCaptureAction();
        $action->setApi($this->api());

        $model = $this->orderModel();

        $exception = $this->captureExpectingFailure($action, $model);

        static::assertStringContainsString('secret_key', $exception->getMessage());

        $request = $model->request();
        static::assertSame('redirect', $request['flow']);
        static::assertSame(1234, $request['amount']);
        static::assertSame('cny', $request['currency']);
        static::assertSame([StripeNotificationExtractor::METADATA_PAYMENT_NO => 'P-1'], $request['metadata']);

        static::assertArrayHasKey('error', $model->response());
    }

    public function testTheSdkFlowIsSelectedByTheSetting(): void
    {
        $action = new StripeCaptureAction();
        $action->setApi($this->api(settings: [StripeCaptureAction::SETTING_FLOW => StripeCaptureAction::FLOW_SDK]));

        $model = $this->orderModel();

        $this->captureExpectingFailure($action, $model);

        static::assertSame('sdk', $model->request()['flow']);
    }

    public function testTheFlowSettingIsTrimmedAndLowercased(): void
    {
        $action = new StripeCaptureAction();
        $action->setApi($this->api(settings: [StripeCaptureAction::SETTING_FLOW => ' SDK ']));

        $model = $this->orderModel();

        $this->captureExpectingFailure($action, $model);

        static::assertSame('sdk', $model->request()['flow']);
    }

    public function testAnUnknownFlowOpensTheCheckoutSession(): void
    {
        $action = new StripeCaptureAction();
        $action->setApi($this->api(settings: [StripeCaptureAction::SETTING_FLOW => 'unknown']));

        $model = $this->orderModel();

        $this->captureExpectingFailure($action, $model);

        // the flow is recorded verbatim, but anything but `sdk` falls back to the hosted checkout
        static::assertSame('unknown', $model->request()['flow']);
    }

    public function testTheOutTradeNoIsAddedToTheMetadata(): void
    {
        $action = new StripeCaptureAction();
        $action->setApi($this->api());

        $model = $this->orderModel();
        $model->offsetSet(PaymentGatewayModel::OUT_TRADE_NO, 'T-1');

        $this->captureExpectingFailure($action, $model);

        static::assertSame(
            [
                StripeNotificationExtractor::METADATA_PAYMENT_NO => 'P-1',
                StripeNotificationExtractor::METADATA_OUT_TRADE_NO => 'T-1',
            ],
            $model->request()['metadata']
        );
    }

    public function testAnExpiryIsEvaluatedAgainstTheClock(): void
    {
        $clock = new MockClock('2024-01-01T00:00:00+00:00');

        $action = new StripeCaptureAction($clock);
        $action->setApi($this->api());

        $model = $this->orderModel();
        $model->offsetSet(PaymentGatewayModel::EXPIRE_AT, $clock->now()->modify('+2 hours'));

        $this->captureExpectingFailure($action, $model);

        static::assertSame('redirect', $model->request()['flow']);
    }

    public function testTheHostedCheckoutAnswersTheUrlOfTheSession(): void
    {
        $this->http->answers[self::SESSION_PATH] = [200, json_encode([
            'id' => 'cs_1',
            'object' => 'checkout.session',
            'url' => 'https://checkout.stripe.test/cs_1',
            'payment_status' => 'unpaid',
            'payment_intent' => 'pi_1',
        ], \JSON_THROW_ON_ERROR)];

        $action = new StripeCaptureAction();
        $action->setApi($this->api(credentials: [StripeApi::SECRET_KEY => 'sk_test_123']));

        $model = $this->orderModel();
        $action->execute(new Capture($model));

        $result = $model->result(PaymentStartResult::class, 'stripe_checkout');

        static::assertInstanceOf(PaymentHandover::class, $result->params);
        static::assertSame(PaymentInteraction::REDIRECT, $result->params->flow);
        static::assertSame('https://checkout.stripe.test/cs_1', $result->params->payUrl);
        static::assertSame('cs_1', $result->params->extra['session_id']);
        static::assertSame('pi_1', $result->params->extra['payment_intent_id']);

        // the answer of the interface is the evidence a support case reads
        static::assertSame('cs_1', $model->response()['id']);
        static::assertSame('unpaid', $model->response()['payment_status']);
        static::assertSame('pi_1', $model->response()['payment_intent']);
    }

    public function testTheSdkFlowAnswersTheClientSecret(): void
    {
        $this->http->answers[self::INTENT_PATH] = [200, json_encode([
            'id' => 'pi_1',
            'object' => 'payment_intent',
            'client_secret' => 'pi_1_secret',
            'status' => 'requires_payment_method',
        ], \JSON_THROW_ON_ERROR)];

        $action = new StripeCaptureAction();
        $action->setApi($this->api(
            credentials: [StripeApi::SECRET_KEY => 'sk_test_123', StripeApi::PUBLISHABLE_KEY => 'pk_test_123'],
            settings: [StripeCaptureAction::SETTING_FLOW => StripeCaptureAction::FLOW_SDK],
        ));

        $model = $this->orderModel();
        $action->execute(new Capture($model));

        $params = $model->result(PaymentStartResult::class, 'stripe_checkout')->params;

        static::assertSame(PaymentInteraction::SDK, $params->flow);
        static::assertSame('pi_1', $params->extra['payment_intent_id']);
        static::assertSame('pi_1_secret', $params->extra['client_secret']);
        static::assertSame('pk_test_123', $params->extra['publishable_key']);
        static::assertSame('requires_payment_method', $model->response()['status']);
    }

    public function testARejectionOfTheInterfaceIsReportedWithItsMessage(): void
    {
        $this->http->answers[self::SESSION_PATH] = [402, json_encode([
            'error' => [
                'type' => 'card_error',
                'code' => 'card_declined',
                'message' => 'Your card was declined.',
            ],
        ], \JSON_THROW_ON_ERROR)];

        $action = new StripeCaptureAction();
        $action->setApi($this->api(credentials: [StripeApi::SECRET_KEY => 'sk_test_123']));

        $model = $this->orderModel();
        $exception = $this->captureExpectingFailure($action, $model);

        // the message of the interface is what a merchant sees, the raw answer is kept as evidence
        static::assertStringContainsString('Your card was declined.', $exception->getMessage());
        static::assertSame('card_declined', $model->response()['error']['code']);
    }

    public function testASessionWithoutUrlIsAGatewayError(): void
    {
        $this->http->answers[self::SESSION_PATH] = [200, json_encode([
            'id' => 'cs_1',
            'object' => 'checkout.session',
            'payment_status' => 'unpaid',
        ], \JSON_THROW_ON_ERROR)];

        $action = new StripeCaptureAction();
        $action->setApi($this->api(credentials: [StripeApi::SECRET_KEY => 'sk_test_123']));

        $exception = $this->captureExpectingFailure($action, $this->orderModel());

        static::assertStringContainsString('checkout url', $exception->getMessage());
    }

    public function testAnIntentWithoutClientSecretIsAGatewayError(): void
    {
        $this->http->answers[self::INTENT_PATH] = [200, json_encode([
            'id' => 'pi_1',
            'object' => 'payment_intent',
            'status' => 'requires_payment_method',
        ], \JSON_THROW_ON_ERROR)];

        $action = new StripeCaptureAction();
        $action->setApi($this->api(
            credentials: [StripeApi::SECRET_KEY => 'sk_test_123'],
            settings: [StripeCaptureAction::SETTING_FLOW => StripeCaptureAction::FLOW_SDK],
        ));

        $exception = $this->captureExpectingFailure($action, $this->orderModel());

        static::assertStringContainsString('client secret', $exception->getMessage());
    }

    private function captureExpectingFailure(StripeCaptureAction $action, PaymentGatewayModel $model): PaymentException
    {
        try {
            $action->execute(new Capture($model));
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());

            return $exception;
        }

        static::fail('The capture was expected to fail without stripe credentials.');
    }

    private function orderModel(): PaymentGatewayModel
    {
        return new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'P-1',
            PaymentGatewayModel::AMOUNT => 1234,
            PaymentGatewayModel::CURRENCY => 'CNY',
            PaymentGatewayModel::SUBJECT => 'Order subject',
        ]);
    }

    /**
     * @param array<string, mixed> $credentials
     * @param array<string, mixed> $settings
     */
    private function api(array $credentials = [], array $settings = []): StripeApi
    {
        $channel = new PaymentProduct('stripe_checkout', 'stripe', 'stripe_checkout', [PaymentInteraction::REDIRECT], 'stripe');

        return new StripeApi(
            new ResolvedPaymentConfig(
                provider: new PaymentProvider(
                    'stripe',
                    'stripe',
                    [],
                    ['stripe_checkout' => $channel],
                    new PaymentGatewayFactory('stripe', StripeApi::class, []),
                    static::createStub(PaymentNotificationExtractorInterface::class),
                ),
                product: $channel,
                credentials: $credentials,
                params: [],
                runtimeParams: [],
                settings: $settings,
                accountId: 'test-account',
            ),
            'https://example.com/notify',
            'https://example.com/return',
        );
    }
}

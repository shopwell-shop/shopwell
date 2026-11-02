<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Integration\Stripe\Action;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Refund;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\RefundResult;
use Shopwell\Core\System\Payment\Integration\Stripe\Action\StripeRefundAction;
use Shopwell\Core\System\Payment\Integration\Stripe\Api\StripeApi;
use Shopwell\Core\System\Payment\Integration\Stripe\Notification\StripeNotificationExtractor;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Order\PaymentRefundStates;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Tests\Unit\Core\System\Payment\Integration\Stripe\FakeStripeHttpClient;
use Stripe\ApiRequestor;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StripeRefundAction::class)]
class StripeRefundActionTest extends TestCase
{
    private const REFUND_PATH = '/v1/refunds';

    private FakeStripeHttpClient $http;

    protected function setUp(): void
    {
        // the sdk of stripe talks through a global http client - the fake below intercepts every
        // request before it would leave the process, and records it for the assertions
        $this->http = new FakeStripeHttpClient();
        ApiRequestor::setHttpClient($this->http);
    }

    public function testSupportsOnlyRefundRequestsWithAPaymentGatewayModel(): void
    {
        $action = new StripeRefundAction();

        static::assertTrue($action->supports(new Refund(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Capture(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Refund(new \stdClass())));
    }

    public function testExecuteRejectsARequestItDoesNotSupport(): void
    {
        $action = new StripeRefundAction();

        $this->expectException(RequestNotSupportedException::class);

        $action->execute(new Capture(new PaymentGatewayModel()));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new StripeRefundAction();

        $this->expectExceptionObject(PaymentException::actionWithoutApi(StripeRefundAction::class));

        $action->execute(new Refund(new PaymentGatewayModel()));
    }

    public function testSetApiRejectsAnApiItDoesNotWorkWith(): void
    {
        $action = new StripeRefundAction();

        $this->expectExceptionObject(PaymentException::unsupportedApi(StripeRefundAction::class, \stdClass::class));

        $action->setApi(new \stdClass());
    }

    public function testExecuteRejectsAPaymentWithoutAPaymentIntent(): void
    {
        $action = new StripeRefundAction();
        $action->setApi($this->api());

        $model = new PaymentGatewayModel([
            PaymentGatewayModel::AMOUNT => 500,
            PaymentGatewayModel::REFUND_NO => 'R-1',
        ]);

        try {
            $action->execute(new Refund($model));
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
            static::assertStringContainsString('cannot be refunded', $exception->getMessage());

            return;
        }

        static::fail('The refund was expected to fail for a payment without a payment intent.');
    }

    public function testExecuteRecordsTheRefundRequestAndWrapsTheMissingCredentialError(): void
    {
        $action = new StripeRefundAction();
        $action->setApi($this->api());

        $model = new PaymentGatewayModel([
            PaymentGatewayModel::OUT_PAYMENT_NO => 'pi_1',
            PaymentGatewayModel::AMOUNT => 500,
            PaymentGatewayModel::REFUND_NO => 'R-1',
            PaymentGatewayModel::REASON => 'broken',
        ]);

        try {
            $action->execute(new Refund($model));
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
            static::assertStringContainsString('secret_key', $exception->getMessage());

            $request = $model->request();
            static::assertSame('pi_1', $request['payment_intent']);
            static::assertSame(500, $request['amount']);
            static::assertSame(
                [
                    StripeNotificationExtractor::METADATA_REFUND_NO => 'R-1',
                    StripeNotificationExtractor::METADATA_REASON => 'broken',
                ],
                $request['metadata']
            );

            static::assertArrayHasKey('error', $model->response());

            return;
        }

        static::fail('The refund was expected to fail without stripe credentials.');
    }

    public function testTheReasonIsTruncatedToFiveHundredCharacters(): void
    {
        $action = new StripeRefundAction();
        $action->setApi($this->api());

        $model = new PaymentGatewayModel([
            PaymentGatewayModel::OUT_PAYMENT_NO => 'pi_1',
            PaymentGatewayModel::AMOUNT => 500,
            PaymentGatewayModel::REFUND_NO => 'R-1',
            PaymentGatewayModel::REASON => str_repeat('a', 600),
        ]);

        try {
            $action->execute(new Refund($model));
        } catch (PaymentException) {
            $metadata = $model->request()['metadata'];
            static::assertIsArray($metadata);
            static::assertSame(500, mb_strlen($metadata[StripeNotificationExtractor::METADATA_REASON]));

            return;
        }

        static::fail('The refund was expected to fail without stripe credentials.');
    }

    public function testNoReasonIsRecordedWhenTheRefundHasNoReason(): void
    {
        $action = new StripeRefundAction();
        $action->setApi($this->api());

        $model = new PaymentGatewayModel([
            PaymentGatewayModel::OUT_PAYMENT_NO => 'pi_1',
            PaymentGatewayModel::AMOUNT => 500,
            PaymentGatewayModel::REFUND_NO => 'R-1',
        ]);

        try {
            $action->execute(new Refund($model));
        } catch (PaymentException) {
            $metadata = $model->request()['metadata'];
            static::assertIsArray($metadata);
            static::assertSame([StripeNotificationExtractor::METADATA_REFUND_NO => 'R-1'], $metadata);

            return;
        }

        static::fail('The refund was expected to fail without stripe credentials.');
    }

    public function testASucceededRefundFinishesTheRefund(): void
    {
        $this->http->answers[self::REFUND_PATH] = [200, json_encode([
            'id' => 're_1',
            'object' => 'refund',
            'status' => 'succeeded',
        ], \JSON_THROW_ON_ERROR)];

        $action = new StripeRefundAction();
        $action->setApi($this->api([StripeApi::SECRET_KEY => 'sk_test_123']));

        $model = new PaymentGatewayModel([
            PaymentGatewayModel::OUT_PAYMENT_NO => 'pi_1',
            PaymentGatewayModel::AMOUNT => 500,
            PaymentGatewayModel::REFUND_NO => 'R-1',
        ]);

        $action->execute(new Refund($model));

        $result = $model->result(RefundResult::class, 'stripe_checkout');

        static::assertSame(PaymentRefundStates::STATE_FINISHED, $result->status);
        static::assertSame('re_1', $result->outRefundId);

        // the refund number of the platform is what makes the call idempotent on the side of stripe
        static::assertSame('R-1', $this->http->headerOf(self::REFUND_PATH, 'Idempotency-Key'));
    }

    public function testAPendingRefundStaysInProcessing(): void
    {
        $this->http->answers[self::REFUND_PATH] = [200, json_encode([
            'id' => 're_1',
            'object' => 'refund',
            'status' => 'pending',
        ], \JSON_THROW_ON_ERROR)];

        $action = new StripeRefundAction();
        $action->setApi($this->api([StripeApi::SECRET_KEY => 'sk_test_123']));

        $model = new PaymentGatewayModel([
            PaymentGatewayModel::OUT_PAYMENT_NO => 'pi_1',
            PaymentGatewayModel::AMOUNT => 500,
            PaymentGatewayModel::REFUND_NO => 'R-1',
        ]);

        $action->execute(new Refund($model));

        static::assertSame(
            PaymentRefundStates::STATE_PROCESSING,
            $model->result(RefundResult::class, 'stripe_checkout')->status
        );
    }

    public function testARejectionOfTheInterfaceIsReportedWithItsMessage(): void
    {
        $this->http->answers[self::REFUND_PATH] = [400, json_encode([
            'error' => [
                'type' => 'invalid_request_error',
                'code' => 'charge_already_refunded',
                'message' => 'Charge has already been refunded.',
            ],
        ], \JSON_THROW_ON_ERROR)];

        $action = new StripeRefundAction();
        $action->setApi($this->api([StripeApi::SECRET_KEY => 'sk_test_123']));

        $model = new PaymentGatewayModel([
            PaymentGatewayModel::OUT_PAYMENT_NO => 'pi_1',
            PaymentGatewayModel::AMOUNT => 500,
            PaymentGatewayModel::REFUND_NO => 'R-1',
        ]);

        try {
            $action->execute(new Refund($model));
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
            static::assertStringContainsString('Charge has already been refunded.', $exception->getMessage());
            static::assertSame('charge_already_refunded', $model->response()['error']['code']);

            return;
        }

        static::fail('The refund was expected to be rejected by stripe.');
    }

    public function testAFailedRefundIsAGatewayError(): void
    {
        $this->http->answers[self::REFUND_PATH] = [200, json_encode([
            'id' => 're_1',
            'object' => 'refund',
            'status' => 'failed',
        ], \JSON_THROW_ON_ERROR)];

        $action = new StripeRefundAction();
        $action->setApi($this->api([StripeApi::SECRET_KEY => 'sk_test_123']));

        $model = new PaymentGatewayModel([
            PaymentGatewayModel::OUT_PAYMENT_NO => 'pi_1',
            PaymentGatewayModel::AMOUNT => 500,
            PaymentGatewayModel::REFUND_NO => 'R-1',
        ]);

        $exception = $this->executeExpectingFailure($action, $model);

        static::assertStringContainsString('failed', $exception->getMessage());
    }

    private function executeExpectingFailure(StripeRefundAction $action, PaymentGatewayModel $model): PaymentException
    {
        try {
            $action->execute(new Refund($model));
        } catch (PaymentException $exception) {
            return $exception;
        }

        static::fail('The refund was expected to fail.');
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function api(array $credentials = []): StripeApi
    {
        $channel = new PaymentChannel('stripe_checkout', 'stripe', 'stripe_checkout', PaymentFlow::REDIRECT);

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
                channel: $channel,
                credentials: $credentials,
                params: [],
                runtimeParams: [],
                settings: [],
            ),
            'https://example.com/notify',
            '',
        );
    }
}

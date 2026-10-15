<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Stripe;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Refund;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeApi;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeNotificationExtractor;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeRefundAction;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StripeRefundAction::class)]
class StripeRefundActionTest extends TestCase
{
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
                mode: 'own',
            ),
            'https://example.com/notify',
            '',
        );
    }
}

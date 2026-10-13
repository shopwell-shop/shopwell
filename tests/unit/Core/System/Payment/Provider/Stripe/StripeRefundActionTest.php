<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Stripe;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Refund;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\PaymentException;
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
    public function testSupportsOnlyRefundRequestsWithAnArrayObjectModel(): void
    {
        $action = new StripeRefundAction();

        static::assertTrue($action->supports(new Refund(new \ArrayObject())));
        static::assertFalse($action->supports(new Capture(new \ArrayObject())));
        static::assertFalse($action->supports(new Refund(new \stdClass())));
    }

    public function testExecuteRejectsARequestItDoesNotSupport(): void
    {
        $action = new StripeRefundAction();

        $this->expectException(RequestNotSupportedException::class);

        $action->execute(new Capture(new \ArrayObject()));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new StripeRefundAction();

        $this->expectExceptionObject(PaymentException::actionWithoutApi(StripeRefundAction::class));

        $action->execute(new Refund(new \ArrayObject()));
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

        $model = new \ArrayObject([
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

        $model = new \ArrayObject([
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

            $request = PaymentGatewayModel::requestOf($model);
            static::assertSame('pi_1', $request['payment_intent']);
            static::assertSame(500, $request['amount']);
            static::assertSame(
                [
                    StripeNotificationExtractor::METADATA_REFUND_NO => 'R-1',
                    StripeNotificationExtractor::METADATA_REASON => 'broken',
                ],
                $request['metadata']
            );

            static::assertArrayHasKey('error', PaymentGatewayModel::responseOf($model));

            return;
        }

        static::fail('The refund was expected to fail without stripe credentials.');
    }

    public function testTheReasonIsTruncatedToFiveHundredCharacters(): void
    {
        $action = new StripeRefundAction();
        $action->setApi($this->api());

        $model = new \ArrayObject([
            PaymentGatewayModel::OUT_PAYMENT_NO => 'pi_1',
            PaymentGatewayModel::AMOUNT => 500,
            PaymentGatewayModel::REFUND_NO => 'R-1',
            PaymentGatewayModel::REASON => str_repeat('a', 600),
        ]);

        try {
            $action->execute(new Refund($model));
        } catch (PaymentException) {
            $metadata = PaymentGatewayModel::requestOf($model)['metadata'];
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

        $model = new \ArrayObject([
            PaymentGatewayModel::OUT_PAYMENT_NO => 'pi_1',
            PaymentGatewayModel::AMOUNT => 500,
            PaymentGatewayModel::REFUND_NO => 'R-1',
        ]);

        try {
            $action->execute(new Refund($model));
        } catch (PaymentException) {
            $metadata = PaymentGatewayModel::requestOf($model)['metadata'];
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
        return new StripeApi([
            AbstractPaymentApi::CONFIG_PROVIDER => 'stripe',
            AbstractPaymentApi::CONFIG_CHANNEL => 'stripe_checkout',
            AbstractPaymentApi::CONFIG_MODE => 'own',
            AbstractPaymentApi::CONFIG_CREDENTIALS => $credentials,
            AbstractPaymentApi::CONFIG_PARAMS => [],
            AbstractPaymentApi::CONFIG_RUNTIME_PARAMS => [],
            AbstractPaymentApi::CONFIG_SETTINGS => [],
            AbstractPaymentApi::CONFIG_NOTIFY_URL => 'https://example.com/notify',
            AbstractPaymentApi::CONFIG_RETURN_URL => '',
        ]);
    }
}

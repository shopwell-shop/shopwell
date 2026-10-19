<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Stripe;

use Payum\Core\Request\Capture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PaymentQuery;
use Shopwell\Core\System\Payment\Gateway\QueryResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeApi;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeQueryAction;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StripeQueryAction::class)]
class StripeQueryActionTest extends TestCase
{
    public function testSupportsOnlyQueryRequestsWithAPaymentGatewayModel(): void
    {
        $action = new StripeQueryAction();

        static::assertTrue($action->supports(new PaymentQuery(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Capture(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new PaymentQuery(new \stdClass())));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new StripeQueryAction();

        $this->expectExceptionObject(PaymentException::actionWithoutApi(StripeQueryAction::class));

        $action->execute(new PaymentQuery(new PaymentGatewayModel()));
    }

    public function testExecuteAnswersAbsentWhenTheCaptureDidNotHandOutAnId(): void
    {
        $model = new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'P-1',
            PaymentGatewayModel::LAST_CHANNEL_RESPONSE => ['status' => 'open'],
        ]);

        $action = new StripeQueryAction();
        $action->setApi($this->api());

        $action->execute(new PaymentQuery($model));

        $request = $model->request();
        static::assertNull($request['payment_intent_id']);
        static::assertNull($request['session_id']);

        $result = $model->result(QueryResult::class, 'stripe_checkout');
        static::assertSame(QueryResult::STATUS_ABSENT, $result->status);
    }

    public function testExecuteAsksForTheIntentOfItsOwnIdAndWrapsAGatewayFailure(): void
    {
        $model = new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'P-1',
            PaymentGatewayModel::LAST_CHANNEL_RESPONSE => ['id' => 'pi_intent_1'],
        ]);

        $action = new StripeQueryAction();
        // without the secret key the sdk fails while it is built, before it would talk to stripe
        $action->setApi($this->api());

        try {
            $action->execute(new PaymentQuery($model));
            static::fail('The query was expected to fail without credentials.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        $request = $model->request();
        static::assertSame('pi_intent_1', $request['payment_intent_id']);
        static::assertNull($request['session_id']);

        static::assertArrayHasKey('error', $model->response());
    }

    public function testExecutePrefersTheIntentOfTheCaptureAnswerOverTheSession(): void
    {
        $model = new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'P-1',
            PaymentGatewayModel::LAST_CHANNEL_RESPONSE => ['id' => 'cs_session_1', 'payment_intent' => 'pi_intent_1'],
        ]);

        $action = new StripeQueryAction();
        // without the secret key the sdk fails while it is built, before it would talk to stripe
        $action->setApi($this->api());

        try {
            $action->execute(new PaymentQuery($model));
            static::fail('The query was expected to fail without credentials.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        $request = $model->request();
        static::assertSame('pi_intent_1', $request['payment_intent_id']);
        static::assertNull($request['session_id']);
    }

    public function testExecuteFallsBackToTheSessionOfTheRedirectFlow(): void
    {
        $model = new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'P-1',
            PaymentGatewayModel::LAST_CHANNEL_RESPONSE => ['id' => 'cs_session_1'],
        ]);

        $action = new StripeQueryAction();
        // without the secret key the sdk fails while it is built, before it would talk to stripe
        $action->setApi($this->api());

        try {
            $action->execute(new PaymentQuery($model));
            static::fail('The query was expected to fail without credentials.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        $request = $model->request();
        static::assertSame('cs_session_1', $request['session_id']);
        static::assertNull($request['payment_intent_id']);
    }

    private function api(): StripeApi
    {
        $channel = new PaymentChannel('stripe_checkout', 'stripe', 'Stripe Checkout', PaymentFlow::REDIRECT);

        return new StripeApi(
            new ResolvedPaymentConfig(
                provider: new PaymentProvider(
                    'stripe',
                    'Stripe',
                    [],
                    ['stripe_checkout' => $channel],
                    new PaymentGatewayFactory('stripe', StripeApi::class, []),
                    static::createStub(PaymentNotificationExtractorInterface::class),
                ),
                channel: $channel,
                credentials: [],
                params: [],
                runtimeParams: [],
                settings: [],
            ),
            'https://example.com/notify',
            '',
        );
    }
}

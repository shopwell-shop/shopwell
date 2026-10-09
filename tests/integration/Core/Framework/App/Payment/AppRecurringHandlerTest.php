<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\App\Payment;

use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopwell\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopwell\Core\Framework\App\Payment\Handler\AppPaymentHandler;
use Shopwell\Core\Framework\App\Payment\Response\PaymentResponse;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
class AppRecurringHandlerTest extends AbstractAppPaymentHandlerTestCase
{
    public function testRecurring(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('recurring');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);

        $response = PaymentResponse::create([
            'status' => OrderTransactionStates::STATE_PAID,
        ]);

        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $paymentHandler = static::getContainer()->get(AppPaymentHandler::class);
        $paymentHandler->recurring($this->getRecurringStruct(), Context::createDefaultContext());

        $request = $this->getLastRequest();
        static::assertNotNull($request);
        $body = $request->getBody()->getContents();

        $appSecret = $this->app->getAppSecret();
        static::assertNotNull($appSecret);

        static::assertTrue($request->hasHeader('shopwell-shop-signature'));
        static::assertSame(hash_hmac('sha256', $body, $appSecret), $request->getHeaderLine('shopwell-shop-signature'));
        static::assertNotSame('', $request->getHeaderLine('sw-version'));
        static::assertNotSame('', $request->getHeaderLine(AuthMiddleware::SHOPWELL_USER_LANGUAGE));
        static::assertNotSame('', $request->getHeaderLine(AuthMiddleware::SHOPWELL_CONTEXT_LANGUAGE));
        static::assertSame('POST', $request->getMethod());
        static::assertJson($body);
        $content = \json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('source', $content);
        static::assertSame([
            'url' => $this->shopUrl,
            'shopId' => $this->shopIdProvider->getShopId()->id,
            'appVersion' => '1.0.0',
            'inAppPurchases' => null,
        ], $content['source']);

        $this->assertOrderTransactionState(OrderTransactionStates::STATE_PAID, $transactionId);
    }

    public function testItFailsOnErrorResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('recurring');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);

        $response = PaymentResponse::create([
            'message' => 'FOO_BAR_ERROR_MESSAGE',
        ]);

        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $paymentHandler = static::getContainer()->get(AppPaymentHandler::class);

        try {
            $paymentHandler->recurring($this->getRecurringStruct(), Context::createDefaultContext());
        } catch (\Throwable $e) {
            static::assertInstanceOf(AppException::class, $e);
            static::assertSame('The app payment process was interrupted due to the following error:
FOO_BAR_ERROR_MESSAGE', $e->getMessage());

            $this->assertOrderTransactionState(OrderTransactionStates::STATE_OPEN, $transactionId);

            return;
        }

        static::fail('Should catch a RecurringException');
    }

    public function testItFailsOnUnsignedResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('recurring');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);

        $response = PaymentResponse::create([]);
        $json = \json_encode($response, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $this->appendNewResponse(new Response(200, [], $json));

        $paymentHandler = static::getContainer()->get(AppPaymentHandler::class);

        try {
            $paymentHandler->recurring($this->getRecurringStruct(), Context::createDefaultContext());
        } catch (\Throwable $e) {
            static::assertInstanceOf(ServerException::class, $e);
            static::assertSame('Could not verify the authenticity of the response', $e->getMessage());

            $this->assertOrderTransactionState(OrderTransactionStates::STATE_OPEN, $transactionId);

            return;
        }

        static::fail('Should catch a RecurringException');
    }

    public function testItFailsOnWronglySignedResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('recurring');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);

        $response = PaymentResponse::create([]);
        $json = \json_encode($response, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $this->appendNewResponse(new Response(200, ['shopwell-app-signature' => 'invalid'], $json));

        $paymentHandler = static::getContainer()->get(AppPaymentHandler::class);

        try {
            $paymentHandler->recurring($this->getRecurringStruct(), Context::createDefaultContext());
        } catch (\Throwable $e) {
            static::assertInstanceOf(ServerException::class, $e);
            static::assertSame('Could not verify the authenticity of the response', $e->getMessage());

            $this->assertOrderTransactionState(OrderTransactionStates::STATE_OPEN, $transactionId);

            return;
        }

        static::fail('Should catch a RecurringException');
    }

    private function getRecurringStruct(): PaymentTransactionStruct
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order.id', $this->ids->get('order')));

        $transactionId = $this->orderTransactionRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertNotNull($transactionId);

        return new PaymentTransactionStruct($transactionId);
    }
}

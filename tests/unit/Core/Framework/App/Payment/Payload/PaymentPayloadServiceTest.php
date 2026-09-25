<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\App\Payment\Payload;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopwell\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopwell\Core\Framework\App\Payload\AppPayloadStruct;
use Shopwell\Core\Framework\App\Payment\Payload\PaymentPayloadService;
use Shopwell\Core\Framework\App\Payment\Payload\Struct\PaymentPayload;
use Shopwell\Core\Framework\App\Payment\Payload\Struct\PaymentPayloadInterface;
use Shopwell\Core\Framework\App\Payment\Response\PaymentResponse;
use Shopwell\Core\Framework\App\ShopId\ShopId;
use Shopwell\Core\Framework\App\ShopId\ShopIdProvider;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Serializer\StructNormalizer;
use Shopwell\Core\Framework\Test\Store\StaticInAppPurchaseFactory;
use Shopwell\Core\Framework\Util\Exception\JsonDecodingException;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AppException::class)]
#[CoversClass(PaymentPayloadService::class)]
class PaymentPayloadServiceTest extends TestCase
{
    private ClientInterface&MockObject $client;

    private AppPayloadServiceHelper&MockObject $helper;

    private PaymentPayloadService $service;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->client = $this->createMock(ClientInterface::class);
        $this->helper = $this->createMock(AppPayloadServiceHelper::class);
        $this->service = new PaymentPayloadService($this->helper, $this->client);
    }

    public function testRequest(): void
    {
        $this->helper->expects($this->never())->method('createRequestOptions');
        $this->client->expects($this->never())->method('request');

        $definition = new OrderTransactionDefinition();
        $definition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definitionInstanceRegistry
            ->method('getByEntityName')
            ->willReturn($definition);

        $shopId = ShopId::v2($this->ids->get('shop-id'));
        $shopIdProvider = static::createStub(ShopIdProvider::class);
        $shopIdProvider
            ->method('getShopId')
            ->willReturn($shopId);

        $entityEncoder = new JsonEntityEncoder(
            new Serializer([new StructNormalizer()], [new JsonEncoder()])
        );

        $appPayloadServiceHelper = new AppPayloadServiceHelper(
            $definitionInstanceRegistry,
            $entityEncoder,
            $shopIdProvider,
            StaticInAppPurchaseFactory::createWithFeatures(),
            'https://test-shop.com',
            new MockClock(),
        );

        $response = \json_encode(['status' => 'paid'], \JSON_THROW_ON_ERROR);

        $client = new Client(['handler' => new MockHandler([new Response(200, [], $response)])]);

        $transaction = new OrderTransactionEntity();
        $transaction->setId($this->ids->get('transaction'));
        $payload = new PaymentPayload($transaction, new OrderEntity());

        $app = new AppEntity();
        $app->setName('foo');
        $app->setId($this->ids->get('app'));
        $app->setVersion('1.0.0');
        $app->setAppSecret('devsecret');

        $service = new PaymentPayloadService($appPayloadServiceHelper, $client);

        $gatewayResponse = $service->request(
            'https://example.com',
            $payload,
            $app,
            PaymentResponse::class,
            Context::createDefaultContext()
        );

        static::assertInstanceOf(PaymentResponse::class, $gatewayResponse);
        static::assertSame('paid', $gatewayResponse->getStatus());
    }

    public function testRequestReturnsExpectedResponse(): void
    {
        $payload = static::createStub(PaymentPayloadInterface::class);
        $app = new AppEntity();
        $app->setName('InsecureApp');
        $app->setVersion('1.0.0');
        $app->setAppSecret('secret');

        $context = Context::createDefaultContext();

        $this->helper
            ->expects($this->once())
            ->method('createRequestOptions')
            ->with($payload, $app)
            ->willReturn($this->buildTestPayload($context));

        $this->client
            ->expects($this->once())
            ->method('request')
            ->with('POST', 'http://example.com', [
                AuthMiddleware::APP_REQUEST_CONTEXT => $context,
                AuthMiddleware::APP_REQUEST_TYPE => [
                    AuthMiddleware::APP_SECRET => 'secret',
                    AuthMiddleware::VALIDATED_RESPONSE => true,
                ],
                'timeout' => PaymentPayloadService::PAYMENT_REQUEST_TIMEOUT,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => '[]',
            ])
            ->willReturn(new Response(200, [], json_encode(['message' => 'foo'], \JSON_THROW_ON_ERROR)));

        $response = $this->service->request(
            'http://example.com',
            $payload,
            $app,
            PaymentResponse::class,
            $context,
        );

        static::assertInstanceOf(PaymentResponse::class, $response);
        static::assertSame('foo', $response->getErrorMessage());
    }

    public function testRequestWithMalformedJsonThrows(): void
    {
        $payload = static::createStub(PaymentPayloadInterface::class);
        $app = new AppEntity();
        $app->setName('InsecureApp');
        $app->setVersion('1.0.0');
        $app->setAppSecret('secret');

        $context = Context::createDefaultContext();

        $this->helper
            ->expects($this->once())
            ->method('createRequestOptions')
            ->willReturn($this->buildTestPayload($context));

        $this->client
            ->expects($this->once())
            ->method('request')
            ->willReturn(new Response(200, [], '{'));

        try {
            $this->service->request(
                'http://example.com',
                $payload,
                $app,
                PaymentResponse::class,
                $context,
            );
            static::fail('Expected malformed payment gateway JSON to be wrapped.');
        } catch (AppException $e) {
            static::assertSame(AppException::APP_PAYMENT_GATEWAY_REQUEST_FAILED, $e->getErrorCode());
            $previous = $e->getPrevious();
            static::assertInstanceOf(JsonDecodingException::class, $previous);
            static::assertInstanceOf(\JsonException::class, $previous->getPrevious());
        }
    }

    private function buildTestPayload(Context $context): AppPayloadStruct
    {
        return new AppPayloadStruct([
            AuthMiddleware::APP_REQUEST_CONTEXT => $context,
            AuthMiddleware::APP_REQUEST_TYPE => [
                AuthMiddleware::APP_SECRET => 'secret',
                AuthMiddleware::VALIDATED_RESPONSE => true,
            ],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 20,
            'body' => '[]',
        ]);
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentWebhookDeliveryCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentWebhookDeliveryEntity;
use Shopwell\Core\System\Payment\ScheduledTask\DeliverPaymentWebhookTaskHandler;
use Shopwell\Core\System\Payment\Service\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Service\PaymentSignatureService;
use Shopwell\Core\System\Payment\Service\PaymentWebhookService;
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DeliverPaymentWebhookTaskHandler::class)]
class DeliverPaymentWebhookTaskHandlerTest extends TestCase
{
    public function testRunRetriesTheDueDeliveries(): void
    {
        $delivery = new PaymentWebhookDeliveryEntity();
        $delivery->setUniqueIdentifier('delivery-id');
        $delivery->id = 'delivery-id';
        $delivery->paymentOrderId = 'order-id';
        $delivery->paymentAppId = 'app-id';
        $delivery->event = PaymentWebhookService::EVENT_ORDER_PAID;
        $delivery->url = 'https://app.example.test/webhook';
        $delivery->payload = ['event' => PaymentWebhookService::EVENT_ORDER_PAID];

        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->once())->method('search')->willReturn(new EntitySearchResult(
            'payment_webhook_delivery',
            1,
            new PaymentWebhookDeliveryCollection([$delivery]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));
        $deliveryRepository->expects($this->once())->method('update');

        $response = static::createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn('ok');

        $client = static::createMock(HttpClientInterface::class);
        $client->expects($this->once())->method('request')->willReturn($response);

        $handler = new DeliverPaymentWebhookTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $this->webhookService($deliveryRepository, $client)
        );

        $handler->run();
    }

    public function testRunDoesNothingWhenNoDeliveryIsDue(): void
    {
        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->once())->method('search')->willReturn(new EntitySearchResult(
            'payment_webhook_delivery',
            0,
            new PaymentWebhookDeliveryCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));
        $deliveryRepository->expects($this->never())->method('update');

        $client = static::createMock(HttpClientInterface::class);
        $client->expects($this->never())->method('request');

        $handler = new DeliverPaymentWebhookTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $this->webhookService($deliveryRepository, $client)
        );

        $handler->run();
    }

    /**
     * @param EntityRepository<PaymentWebhookDeliveryCollection> $deliveryRepository
     */
    private function webhookService(EntityRepository $deliveryRepository, HttpClientInterface $client): PaymentWebhookService
    {
        $cipher = new PaymentCredentialCipher('test-secret');

        $app = new PaymentAppEntity();
        $app->setUniqueIdentifier('app-id');
        $app->id = 'app-id';
        $app->technicalName = 'demo-app';
        $app->appSecret = $cipher->encryptValue('app-secret');
        $app->active = true;

        $appRepository = static::createStub(EntityRepository::class);
        $appRepository->method('search')->willReturn(new EntitySearchResult(
            'payment_app',
            1,
            new PaymentAppCollection([$app]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        return new PaymentWebhookService(
            $deliveryRepository,
            $appRepository,
            new PaymentSignatureService(),
            $cipher,
            $client,
            new MockClock('2024-01-01T00:00:00+00:00'),
            static::createStub(LoggerInterface::class)
        );
    }
}

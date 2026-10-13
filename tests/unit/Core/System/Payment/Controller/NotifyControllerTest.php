<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Controller\NotifyController;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentNotificationCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentOrderCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentRefundCollection;
use Shopwell\Core\System\Payment\Provider\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Service\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Service\PaymentNotificationService;
use Shopwell\Core\System\Payment\Struct\PaymentNotificationAck;
use Shopwell\Core\System\Payment\Struct\PaymentNotificationData;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NotifyController::class)]
class NotifyControllerTest extends TestCase
{
    public function testAnUnknownProviderIsNotFound(): void
    {
        $controller = $this->controller(PaymentControllerFixture::providerRegistry());

        $response = $controller->notify($this->notification('{}'), 'unknown', 'PG-1');

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        static::assertSame('', $response->getContent());
    }

    public function testANotificationThatCannotBeAppliedIsStillAcknowledged(): void
    {
        // the extractor cannot identify the order, so the notification is rejected - and still has to
        // be acknowledged, otherwise the interface would repeat it forever
        $extractor = static::createStub(PaymentNotificationExtractorInterface::class);
        $extractor->method('extract')->willReturn(new PaymentNotificationData());
        $extractor->method('acknowledge')->willReturn(new PaymentNotificationAck('success'));

        $controller = $this->controller(PaymentControllerFixture::providerRegistry(PaymentControllerFixture::provider('alipay', [], $extractor)));

        $response = $controller->notify($this->notification('{"out_trade_no":"T-1"}'), 'alipay', 'PG-1');

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('success', $response->getContent());
        static::assertSame('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
    }

    public function testAJsonAcknowledgementOfTheChannelIsUsed(): void
    {
        $extractor = static::createStub(PaymentNotificationExtractorInterface::class);
        $extractor->method('extract')->willReturn(new PaymentNotificationData());
        $extractor->method('acknowledge')->willReturn(new PaymentNotificationAck('{"code":"SUCCESS"}', 'application/json'));

        $controller = $this->controller(PaymentControllerFixture::providerRegistry(PaymentControllerFixture::provider('wechat', [], $extractor)));

        $response = $controller->notify($this->notification('{}'), 'wechat', null);

        static::assertSame('{"code":"SUCCESS"}', $response->getContent());
        static::assertSame('application/json', $response->headers->get('Content-Type'));
    }

    public function testSomethingBrokenOnOurSideAsksTheChannelToRetry(): void
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('create')->willThrowException(new \RuntimeException('storage is broken'));

        $controller = $this->controller(
            PaymentControllerFixture::providerRegistry(PaymentControllerFixture::provider('alipay', [], $this->extractor())),
            $repository
        );

        $response = $controller->notify($this->notification('{}'), 'alipay', 'PG-1');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        static::assertSame('', $response->getContent());
    }

    private function extractor(): PaymentNotificationExtractorInterface
    {
        $extractor = static::createStub(PaymentNotificationExtractorInterface::class);
        $extractor->method('extract')->willReturn(new PaymentNotificationData());
        $extractor->method('acknowledge')->willReturn(new PaymentNotificationAck('success'));

        return $extractor;
    }

    private function notification(string $body): Request
    {
        return new Request([], [], [], [], [], ['REQUEST_URI' => '/payment-api/v1/notify/alipay/PG-1'], $body);
    }

    /**
     * @param EntityRepository<PaymentNotificationCollection>|null $notificationRepository
     */
    private function controller(PaymentProviderRegistry $providerRegistry, ?EntityRepository $notificationRepository = null): NotifyController
    {
        return new NotifyController(
            $providerRegistry,
            $this->notificationServiceFor($providerRegistry, $notificationRepository ?? PaymentControllerFixture::repository(PaymentNotificationCollection::class)),
            static::createStub(LoggerInterface::class),
        );
    }

    /**
     * @param EntityRepository<PaymentNotificationCollection> $notificationRepository
     */
    private function notificationServiceFor(PaymentProviderRegistry $providerRegistry, EntityRepository $notificationRepository): PaymentNotificationService
    {
        $resolver = $this->resolver($providerRegistry);
        $orderService = PaymentControllerFixture::orderService(PaymentControllerFixture::repository(PaymentOrderCollection::class), $resolver);
        $refundService = PaymentControllerFixture::refundService(PaymentControllerFixture::repository(PaymentRefundCollection::class), $resolver, $orderService);

        return PaymentControllerFixture::notificationService($providerRegistry, $notificationRepository, $orderService, $refundService, $resolver);
    }

    private function resolver(PaymentProviderRegistry $providerRegistry): PaymentConfigResolver
    {
        return PaymentControllerFixture::configResolver($providerRegistry, PaymentControllerFixture::channelRegistry($providerRegistry));
    }
}

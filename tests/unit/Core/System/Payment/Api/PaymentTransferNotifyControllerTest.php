<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\PaymentTransferNotifyController;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Exception\PaymentNotificationException;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationCollection;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationAck;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationProcessor;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationVerifierInterface;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationVerifierRegistry;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferCollection;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferReconciler;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStateHandler;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Tests\Unit\Core\System\Payment\Account\_fixtures\PaymentAccountLockStub;
use Shopwell\Tests\Unit\Core\System\Payment\NumberRangeFixture;
use Shopwell\Tests\Unit\Core\System\Payment\Transfer\PaymentTransferRulesFixture;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The endpoint is called by the interfaces themselves and has to answer in the language of the
 * channels: an unknown interface or app is a 404 without detail, a notification that cannot be
 * applied is still acknowledged, and only a defect of this installation asks for a retry.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentTransferNotifyController::class)]
class PaymentTransferNotifyControllerTest extends TestCase
{
    private const APP_ID = 'app-id';

    private const NOW = '2024-01-01T00:00:00+00:00';

    public function testAnUnknownProviderIsNotFound(): void
    {
        $notificationRepository = static::createMock(EntityRepository::class);
        $notificationRepository->expects($this->never())->method('create');

        $controller = $this->controller(
            $notificationRepository,
            $this->appRepository(),
            $this->verifier(),
            withVerifier: false
        );

        $response = $controller->notify($this->request(), 'unknown', self::APP_ID);

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        static::assertSame('', $response->getContent());
    }

    public function testAnUnknownAppIsNotFound(): void
    {
        $notificationRepository = static::createMock(EntityRepository::class);
        $notificationRepository->expects($this->never())->method('create');

        $controller = $this->controller($notificationRepository, $this->emptyAppRepository(), $this->verifier());

        $response = $controller->notify($this->request(), 'alipay', self::APP_ID);

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    /**
     * The notification cannot be applied by repeating it, so it is acknowledged with the ack of the
     * interface - a channel that gets an error would retry it forever.
     */
    public function testARejectedNotificationIsAcknowledged(): void
    {
        $controller = $this->controller(
            $this->notificationRepository(),
            $this->appRepository(),
            $this->verifier(throwOnVerify: PaymentNotificationException::notificationInvalid('alipay', 'broken'))
        );

        $response = $controller->notify($this->request(), 'alipay', self::APP_ID);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('success', $response->getContent());
        static::assertSame('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
    }

    public function testAValidNotificationIsAcknowledged(): void
    {
        $controller = $this->controller(
            $this->notificationRepository(),
            $this->appRepository(),
            $this->verifier(valid: false)
        );

        $response = $controller->notify($this->request(), 'alipay', self::APP_ID);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('success', $response->getContent());
    }

    /**
     * A broken installation is the only reason to ask for a retry, so a defect that is not the
     * interface's fault answers 5xx.
     */
    public function testATransientPaymentFailureRequestsNotificationRetry(): void
    {
        $controller = $this->controller(
            $this->notificationRepository(),
            $this->appRepository(),
            $this->verifier(throwOnVerify: PaymentGatewayException::gatewayError('alipay', 'upstream unavailable'))
        );
        $response = $controller->notify($this->request(), 'alipay', self::APP_ID);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        static::assertSame('', $response->getContent());
    }

    public function testAnUnexpectedErrorIsAServerError(): void
    {
        $appRepository = $this->failingAppRepository(new \RuntimeException('database is gone'));

        $controller = $this->controller($this->notificationRepository(), $appRepository, $this->verifier());

        $response = $controller->notify($this->request(), 'alipay', self::APP_ID);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        static::assertSame('', $response->getContent());
    }

    /**
     * @param EntityRepository<PaymentNotificationCollection> $notificationRepository
     * @param EntityRepository<PaymentAppCollection> $appRepository
     */
    private function controller(
        EntityRepository $notificationRepository,
        EntityRepository $appRepository,
        PaymentTransferNotificationVerifierInterface $verifier,
        bool $withVerifier = true
    ): PaymentTransferNotifyController {
        $cipher = new PaymentCredentialCipher('test-secret');

        $configResolver = static::createStub(PaymentConfigResolver::class);
        $configResolver->method('accountCredentials')->willReturn(['alipay_public_key' => 'key']);

        $transferRepository = $this->transferRepository();
        $transferService = new PaymentTransferService(
            $transferRepository,
            static::createStub(EntityRepository::class),
            new PaymentTransferStateHandler(static::createStub(StateMachineRegistry::class)),
            new PaymentTransferRecipientService(static::createStub(EntityRepository::class), $cipher, new MockClock(self::NOW), PaymentTransferRulesFixture::registry()),
            $cipher,
            static::createStub(InitialStateIdLoader::class),
            new MockClock(self::NOW),
            PaymentTransferRulesFixture::registry(),
            NumberRangeFixture::generator('PT1001'),
            $configResolver,
            new PaymentAccountLockStub(),
        );

        $processor = new PaymentTransferNotificationProcessor(
            $notificationRepository,
            $appRepository,
            new PaymentTransferNotificationVerifierRegistry($withVerifier ? [$verifier] : []),
            $configResolver,
            $transferService,
            new PaymentTransferReconciler(
                $configResolver,
                $transferService,
                $transferRepository,
                static::createStub(EntityRepository::class),
                new MockClock(self::NOW),
                static::createStub(LoggerInterface::class)
            )
        );

        return new PaymentTransferNotifyController(
            new PaymentTransferNotificationVerifierRegistry($withVerifier ? [$verifier] : []),
            $processor,
            static::createStub(LoggerInterface::class)
        );
    }

    private function request(): Request
    {
        return Request::create(
            '/payment-api/v1/transfer-notify/alipay/' . self::APP_ID,
            Request::METHOD_POST,
            server: ['REMOTE_ADDR' => '1.2.3.4'],
            content: '{"out_biz_no":"PO-1"}'
        );
    }

    private function verifier(
        bool $valid = true,
        ?\Throwable $throwOnVerify = null
    ): PaymentTransferNotificationVerifierInterface {
        $verifier = static::createStub(PaymentTransferNotificationVerifierInterface::class);
        $verifier->method('provider')->willReturn('alipay');
        $verifier->method('acknowledge')->willReturn(new PaymentNotificationAck('success'));

        if ($throwOnVerify !== null) {
            $verifier->method('verify')->willThrowException($throwOnVerify);
        } else {
            $verifier->method('verify')->willReturn($valid);
            $verifier->method('outNoOf')->willReturn('PO-1');
        }

        return $verifier;
    }

    /**
     * @return EntityRepository<PaymentNotificationCollection>
     */
    private function notificationRepository(): EntityRepository
    {
        return static::createStub(EntityRepository::class);
    }

    /**
     * @return EntityRepository<PaymentAppCollection>
     */
    private function failingAppRepository(\Throwable $exception): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willThrowException($exception);

        return $repository;
    }

    /**
     * @return EntityRepository<PaymentAppCollection>
     */
    private function appRepository(): EntityRepository
    {
        $app = new PaymentAppEntity();
        $app->setUniqueIdentifier(self::APP_ID);
        $app->id = self::APP_ID;
        $app->technicalName = 'test-app';

        return $this->repositoryWith(new EntitySearchResult(
            'payment_app',
            1,
            new PaymentAppCollection([$app]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));
    }

    /**
     * @return EntityRepository<PaymentAppCollection>
     */
    private function emptyAppRepository(): EntityRepository
    {
        return $this->repositoryWith(new EntitySearchResult(
            'payment_app',
            0,
            new PaymentAppCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));
    }

    /**
     * @return EntityRepository<PaymentTransferCollection>
     */
    private function transferRepository(): EntityRepository
    {
        $transfer = new PaymentTransferEntity();
        $transfer->id = 'transfer-id';
        $transfer->setUniqueIdentifier($transfer->id);
        $transfer->accountId = 'account-id';

        return $this->repositoryWith(new EntitySearchResult(
            'payment_transfer',
            1,
            new PaymentTransferCollection([$transfer]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));
    }

    /**
     * @template TCollection of EntityCollection
     *
     * @param EntitySearchResult<TCollection> $result
     *
     * @return EntityRepository<TCollection>
     */
    private function repositoryWith(EntitySearchResult $result): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($result);

        return $repository;
    }
}

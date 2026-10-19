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
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationCollection;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationAck;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationProcessor;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationVerifierInterface;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationVerifierRegistry;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferCollection;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferReconciler;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStateHandler;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
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
            $this->verifier(throwOnVerify: PaymentException::notificationInvalid('alipay', 'broken'))
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

        $provider = new PaymentProvider(
            technicalName: 'alipay',
            label: 'Alipay',
            credentials: [],
            channels: [],
            gatewayFactory: static::createStub(PaymentGatewayFactory::class),
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
        );

        $appProvider = new PaymentAppProviderEntity();
        $appProvider->setUniqueIdentifier('app-provider-id');
        $appProvider->paymentAppId = self::APP_ID;
        $appProvider->providerTechnicalName = 'alipay';
        $appProvider->enabled = true;
        $appProvider->credentials = $cipher->encrypt(['alipay_public_key' => 'key']);

        $appProviderRepository = $this->appProviderRepository($appProvider);

        $providerRegistry = new PaymentProviderRegistry([$provider]);

        $configResolver = new PaymentConfigResolver(
            $providerRegistry,
            new PaymentChannelRegistry($providerRegistry),
            $appProviderRepository,
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            $cipher
        );

        $transferRepository = $this->transferRepository();
        $transferService = new PaymentTransferService(
            $transferRepository,
            static::createStub(EntityRepository::class),
            new PaymentTransferStateHandler(static::createStub(StateMachineRegistry::class)),
            new PaymentTransferRecipientService(static::createStub(EntityRepository::class), $cipher, new MockClock(self::NOW)),
            $cipher,
            static::createStub(InitialStateIdLoader::class),
            new MockClock(self::NOW)
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
     * @return EntityRepository<PaymentAppProviderCollection>
     */
    private function appProviderRepository(PaymentAppProviderEntity $appProvider): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult(
            'payment_app_provider',
            1,
            new PaymentAppProviderCollection([$appProvider]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        return $repository;
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
        return $this->repositoryWith(new EntitySearchResult(
            'payment_transfer',
            0,
            new PaymentTransferCollection([]),
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

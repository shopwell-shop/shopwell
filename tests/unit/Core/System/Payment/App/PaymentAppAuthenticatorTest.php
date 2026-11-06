<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\System\Payment\App\PaymentAppAuthenticator;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\App\PaymentSignatureService;
use Shopwell\Core\System\Payment\Exception\PaymentAppException;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentAppAuthenticator::class)]
class PaymentAppAuthenticatorTest extends TestCase
{
    private const APP_ID = 'app-1';
    private const SECRET = 'the-app-secret';
    private const PATH = '/payment-api/v1/orders';
    private const METHOD = 'POST';
    private const NONCE = 'nonce-1';

    private MockClock $clock;

    private PaymentSignatureService $signatureService;

    private PaymentCredentialCipher $cipher;

    private LockFactory $lockFactory;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2024-01-01T00:00:00+00:00');
        $this->signatureService = new PaymentSignatureService();
        $this->cipher = new PaymentCredentialCipher('application-secret');
        $this->lockFactory = new LockFactory(new InMemoryStore());
    }

    public function testAuthenticateReturnsTheAppForAValidRequest(): void
    {
        $app = $this->app();
        $authenticator = $this->authenticator($app);

        $params = $this->signedParams();

        static::assertSame($app, $authenticator->authenticate($this->request(), $params));
    }

    public function testConfiguredAppRateLimitIsEnforcedAcrossSignedRequests(): void
    {
        $app = $this->app();
        $app->rateLimit = 1;
        $authenticator = $this->authenticator($app);
        $windowStartedBefore = microtime(true);
        $authenticator->authenticate($this->request(), $this->signedParams());
        $windowStartedAfter = microtime(true);

        $params = $this->signedParams();
        $params[PaymentSignatureService::NONCE] = 'second-request';
        $params[PaymentSignatureService::SIGN] = $this->signatureService->sign(self::SECRET, self::APP_ID, $this->clock->now()->getTimestamp(), 'second-request', self::METHOD, self::PATH, $params);

        $rejectedBefore = microtime(true);
        try {
            $authenticator->authenticate($this->request(), $params);
            static::fail('The second request must be rate limited.');
        } catch (PaymentAppException $exception) {
            static::assertSame(429, $exception->getStatusCode());
            static::assertSame(PaymentAppException::APP_RATE_LIMIT_EXCEEDED, $exception->getErrorCode());
            $seconds = $exception->getParameter('seconds');
            static::assertIsInt($seconds);
            static::assertGreaterThan(0, $seconds);
            // Retry-After rounds the window's expiry up to a whole second.
            static::assertGreaterThanOrEqual(max(1, (int) floor($windowStartedBefore + 60) - time()), $seconds);
            static::assertLessThanOrEqual((int) ceil($windowStartedAfter + 60) - (int) floor($rejectedBefore), $seconds);
            static::assertSame(['Retry-After' => (string) $seconds], $exception->getHeaders());
        }
    }

    public function testAuthenticateRejectsAMissingAuthParameter(): void
    {
        $authenticator = $this->authenticator($this->app());

        $this->expectExceptionObject(PaymentAppException::missingAuthParameters([PaymentSignatureService::APP_ID]));

        $authenticator->authenticate($this->request(), [PaymentSignatureService::TIMESTAMP => '1']);
    }

    public function testAuthenticateRejectsAnEmptyAuthParameter(): void
    {
        $authenticator = $this->authenticator($this->app());

        $params = $this->signedParams();
        $params[PaymentSignatureService::NONCE] = '   ';

        $this->expectExceptionObject(PaymentAppException::missingAuthParameters([PaymentSignatureService::NONCE]));

        $authenticator->authenticate($this->request(), $params);
    }

    public function testAuthenticateRejectsANonNumericTimestamp(): void
    {
        $authenticator = $this->authenticator($this->app());

        $params = $this->signedParams();
        $params[PaymentSignatureService::TIMESTAMP] = 'not-a-number';

        $this->expectExceptionObject(PaymentAppException::expiredTimestamp(0, PaymentAppAuthenticator::TIMESTAMP_WINDOW));

        $authenticator->authenticate($this->request(), $params);
    }

    public function testAuthenticateRejectsATimestampOutsideTheWindow(): void
    {
        $authenticator = $this->authenticator($this->app());

        $timestamp = $this->clock->now()->getTimestamp() - 400;

        $this->expectExceptionObject(PaymentAppException::expiredTimestamp($timestamp, PaymentAppAuthenticator::TIMESTAMP_WINDOW));

        $authenticator->authenticate($this->request(), $this->signedParams($timestamp));
    }

    public function testAuthenticateAcceptsATimestampAtTheEdgeOfTheWindow(): void
    {
        $app = $this->app();
        $authenticator = $this->authenticator($app);

        $timestamp = $this->clock->now()->getTimestamp() - PaymentAppAuthenticator::TIMESTAMP_WINDOW;

        static::assertSame($app, $authenticator->authenticate($this->request(), $this->signedParams($timestamp)));
    }

    public function testAuthenticateRejectsAnUnknownApp(): void
    {
        $authenticator = $this->authenticator(null);

        $this->expectExceptionObject(PaymentAppException::appNotFound(self::APP_ID));

        $authenticator->authenticate($this->request(), $this->signedParams());
    }

    public function testAuthenticateRejectsADisabledApp(): void
    {
        $authenticator = $this->authenticator($this->app(active: false));

        $this->expectExceptionObject(PaymentAppException::appDisabled(self::APP_ID));

        $authenticator->authenticate($this->request(), $this->signedParams());
    }

    /**
     * The tenant of the shop is no caller, whatever secret it carries: the api must answer it like an
     * app that does not exist, so nobody learns that this technical name is taken.
     */
    public function testAuthenticateRejectsThePlatformTenant(): void
    {
        $authenticator = $this->authenticator($this->app(type: PaymentAppEntity::TYPE_PLATFORM));

        $this->expectExceptionObject(PaymentAppException::appNotFound(self::APP_ID));

        $authenticator->authenticate($this->request(), $this->signedParams());
    }

    /**
     * The kind of the tenant is checked before its state: were the order reversed, a disabled tenant
     * of the shop would answer "disabled" and thereby confirm that it exists.
     */
    public function testAuthenticateRejectsADisabledPlatformTenantAsUnknown(): void
    {
        $authenticator = $this->authenticator($this->app(active: false, type: PaymentAppEntity::TYPE_PLATFORM));

        $this->expectExceptionObject(PaymentAppException::appNotFound(self::APP_ID));

        $authenticator->authenticate($this->request(), $this->signedParams());
    }

    public function testAuthenticateRejectsAWrongSignature(): void
    {
        $authenticator = $this->authenticator($this->app());

        $params = $this->signedParams();
        $params[PaymentSignatureService::SIGN] = 'the wrong signature';

        $this->expectExceptionObject(PaymentAppException::invalidSignature(self::APP_ID));

        $authenticator->authenticate($this->request(), $params);
    }

    public function testAuthenticateRejectsAReplayedNonce(): void
    {
        $authenticator = $this->authenticator($this->app());
        $params = $this->signedParams();

        $authenticator->authenticate($this->request(), $params);

        $this->expectExceptionObject(PaymentAppException::invalidSignature(self::APP_ID));

        $authenticator->authenticate($this->request(), $params);
    }

    public function testFutureTimestampNonceRemainsClaimedAtTheLastAcceptedSecond(): void
    {
        $authenticator = $this->authenticator($this->app());
        $params = $this->signedParams($this->clock->now()->getTimestamp() + PaymentAppAuthenticator::TIMESTAMP_WINDOW);

        $authenticator->authenticate($this->request(), $params);
        $this->clock->sleep(2 * PaymentAppAuthenticator::TIMESTAMP_WINDOW);

        $this->expectExceptionObject(PaymentAppException::invalidSignature(self::APP_ID));
        $authenticator->authenticate($this->request(), $params);
    }

    public function testContendedNonceClaimRejectsTheConcurrentRequest(): void
    {
        $authenticator = $this->authenticator($this->app());
        $lock = $this->lockFactory->createLock('shopwell.payment.nonce.' . Hasher::hash(self::APP_ID . '|' . self::NONCE));
        static::assertTrue($lock->acquire());

        try {
            $this->expectExceptionObject(PaymentAppException::invalidSignature(self::APP_ID));
            $authenticator->authenticate($this->request(), $this->signedParams());
        } finally {
            $lock->release();
        }
    }

    public function testFailedNoncePersistenceRejectsTheRequestAndReleasesTheLock(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn((new ArrayAdapter(clock: $this->clock))->getItem('nonce'));
        $cache->expects($this->once())->method('save')->willReturn(false);
        $authenticator = $this->authenticator($this->app(), $cache);

        try {
            $authenticator->authenticate($this->request(), $this->signedParams());
            static::fail('A request without a persisted nonce must fail closed.');
        } catch (PaymentAppException $exception) {
            static::assertEquals(PaymentAppException::invalidSignature(self::APP_ID), $exception);
        }

        $lock = $this->lockFactory->createLock('shopwell.payment.nonce.' . Hasher::hash(self::APP_ID . '|' . self::NONCE));
        static::assertTrue($lock->acquire());
        $lock->release();
    }

    public function testDisallowedIpDoesNotConsumeTheNonce(): void
    {
        $app = $this->app(allowedIps: ['10.0.0.1']);
        $authenticator = $this->authenticator($app);
        $params = $this->signedParams();

        try {
            $authenticator->authenticate($this->request(), $params);
            static::fail('The first IP must be refused.');
        } catch (PaymentAppException $exception) {
            static::assertEquals(PaymentAppException::invalidSignature(self::APP_ID), $exception);
        }

        $allowedRequest = Request::create(self::PATH, self::METHOD, server: ['REMOTE_ADDR' => '10.0.0.1']);
        static::assertSame($app, $authenticator->authenticate($allowedRequest, $params));
    }

    public function testAuthenticateRejectsARequestFromADisallowedIp(): void
    {
        $authenticator = $this->authenticator($this->app(allowedIps: ['10.0.0.1']));

        $this->expectExceptionObject(PaymentAppException::invalidSignature(self::APP_ID));

        $authenticator->authenticate($this->request(), $this->signedParams());
    }

    public function testAuthenticateAllowsARequestFromAnAllowedIp(): void
    {
        $app = $this->app(allowedIps: ['127.0.0.1']);
        $authenticator = $this->authenticator($app);

        static::assertSame($app, $authenticator->authenticate($this->request(), $this->signedParams()));
    }

    public function testAuthParametersOfMergesTheQueryIntoTheBody(): void
    {
        $authenticator = $this->authenticator($this->app());

        $request = Request::create(self::PATH, 'GET', [
            PaymentSignatureService::APP_ID => 'app-1',
            'filter' => 'open',
        ]);

        $params = $authenticator->authParametersOf($request, ['order_no' => 'P-1', PaymentSignatureService::APP_ID => 'body-app']);

        static::assertSame('body-app', $params[PaymentSignatureService::APP_ID]);
        static::assertSame('open', $params['filter']);
        static::assertSame('P-1', $params['order_no']);
    }

    public function testAuthParametersOfWithoutABodyUsesTheQueryOnly(): void
    {
        $authenticator = $this->authenticator($this->app());

        $request = Request::create(self::PATH, 'GET', ['order_no' => 'P-1']);

        static::assertSame(['order_no' => 'P-1'], $authenticator->authParametersOf($request));
    }

    /**
     * @return array<string, mixed>
     */
    private function signedParams(?int $timestamp = null): array
    {
        $timestamp ??= $this->clock->now()->getTimestamp();

        $params = [
            PaymentSignatureService::APP_ID => self::APP_ID,
            PaymentSignatureService::TIMESTAMP => (string) $timestamp,
            PaymentSignatureService::NONCE => self::NONCE,
            PaymentSignatureService::SIGN => 'placeholder',
            'order_no' => 'P-1',
        ];

        $params[PaymentSignatureService::SIGN] = $this->signatureService->sign(
            self::SECRET,
            self::APP_ID,
            $timestamp,
            self::NONCE,
            self::METHOD,
            self::PATH,
            $params
        );

        return $params;
    }

    private function request(): Request
    {
        return Request::create(self::PATH, self::METHOD);
    }

    /**
     * @param list<string>|null $allowedIps
     */
    private function app(bool $active = true, ?array $allowedIps = null, string $type = PaymentAppEntity::TYPE_EXTERNAL): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->setUniqueIdentifier(self::APP_ID);
        $app->id = self::APP_ID;
        $app->technicalName = self::APP_ID;
        $app->name = 'Test app';
        $app->type = $type;
        $app->active = $active;
        $app->appSecret = $this->cipher->encryptValue(self::SECRET);
        $app->allowedIps = $allowedIps;

        return $app;
    }

    private function authenticator(?PaymentAppEntity $app, ?CacheItemPoolInterface $cache = null): PaymentAppAuthenticator
    {
        $apps = new PaymentAppCollection($app === null ? [] : [$app]);

        return new PaymentAppAuthenticator(
            new StaticEntityRepository([$apps, $apps]),
            $this->signatureService,
            $this->cipher,
            $cache ?? new ArrayAdapter(clock: $this->clock),
            $this->lockFactory,
            $this->clock,
            PaymentAppAuthenticator::TIMESTAMP_WINDOW
        );
    }
}

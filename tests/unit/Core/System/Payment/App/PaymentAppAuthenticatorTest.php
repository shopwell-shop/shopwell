<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppAuthenticator;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\App\PaymentSignatureService;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;

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

    protected function setUp(): void
    {
        $this->clock = new MockClock('2024-01-01T00:00:00+00:00');
        $this->signatureService = new PaymentSignatureService();
        $this->cipher = new PaymentCredentialCipher('application-secret');
    }

    public function testAuthenticateReturnsTheAppForAValidRequest(): void
    {
        $app = $this->app();
        $authenticator = $this->authenticator($app);

        $params = $this->signedParams();

        static::assertSame($app, $authenticator->authenticate($this->request(), $params));
    }

    public function testAuthenticateRejectsAMissingAuthParameter(): void
    {
        $authenticator = $this->authenticator($this->app());

        $this->expectExceptionObject(PaymentException::missingAuthParameters([PaymentSignatureService::APP_ID]));

        $authenticator->authenticate($this->request(), [PaymentSignatureService::TIMESTAMP => '1']);
    }

    public function testAuthenticateRejectsAnEmptyAuthParameter(): void
    {
        $authenticator = $this->authenticator($this->app());

        $params = $this->signedParams();
        $params[PaymentSignatureService::NONCE] = '   ';

        $this->expectExceptionObject(PaymentException::missingAuthParameters([PaymentSignatureService::NONCE]));

        $authenticator->authenticate($this->request(), $params);
    }

    public function testAuthenticateRejectsANonNumericTimestamp(): void
    {
        $authenticator = $this->authenticator($this->app());

        $params = $this->signedParams();
        $params[PaymentSignatureService::TIMESTAMP] = 'not-a-number';

        $this->expectExceptionObject(PaymentException::expiredTimestamp(0, PaymentAppAuthenticator::TIMESTAMP_WINDOW));

        $authenticator->authenticate($this->request(), $params);
    }

    public function testAuthenticateRejectsATimestampOutsideTheWindow(): void
    {
        $authenticator = $this->authenticator($this->app());

        $timestamp = $this->clock->now()->getTimestamp() - 400;

        $this->expectExceptionObject(PaymentException::expiredTimestamp($timestamp, PaymentAppAuthenticator::TIMESTAMP_WINDOW));

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

        $this->expectExceptionObject(PaymentException::appNotFound(self::APP_ID));

        $authenticator->authenticate($this->request(), $this->signedParams());
    }

    public function testAuthenticateRejectsADisabledApp(): void
    {
        $authenticator = $this->authenticator($this->app(active: false));

        $this->expectExceptionObject(PaymentException::appDisabled(self::APP_ID));

        $authenticator->authenticate($this->request(), $this->signedParams());
    }

    public function testAuthenticateRejectsAWrongSignature(): void
    {
        $authenticator = $this->authenticator($this->app());

        $params = $this->signedParams();
        $params[PaymentSignatureService::SIGN] = 'the wrong signature';

        $this->expectExceptionObject(PaymentException::invalidSignature(self::APP_ID));

        $authenticator->authenticate($this->request(), $params);
    }

    public function testAuthenticateRejectsAReplayedNonce(): void
    {
        $authenticator = $this->authenticator($this->app());
        $params = $this->signedParams();

        $authenticator->authenticate($this->request(), $params);

        $this->expectExceptionObject(PaymentException::invalidSignature(self::APP_ID));

        $authenticator->authenticate($this->request(), $params);
    }

    public function testAuthenticateRejectsARequestFromADisallowedIp(): void
    {
        $authenticator = $this->authenticator($this->app(allowedIps: ['10.0.0.1']));

        $this->expectExceptionObject(PaymentException::invalidSignature(self::APP_ID));

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
    private function app(bool $active = true, ?array $allowedIps = null): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->setUniqueIdentifier(self::APP_ID);
        $app->id = self::APP_ID;
        $app->technicalName = self::APP_ID;
        $app->name = 'Test app';
        $app->active = $active;
        $app->appSecret = $this->cipher->encryptValue(self::SECRET);
        $app->allowedIps = $allowedIps;

        return $app;
    }

    private function authenticator(?PaymentAppEntity $app): PaymentAppAuthenticator
    {
        return new PaymentAppAuthenticator(
            $this->appRepository($app),
            $this->signatureService,
            $this->cipher,
            new ArrayAdapter(),
            $this->clock,
            PaymentAppAuthenticator::TIMESTAMP_WINDOW
        );
    }

    /**
     * @return EntityRepository<PaymentAppCollection>
     */
    private function appRepository(?PaymentAppEntity $app): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($this->searchResult($app));

        return $repository;
    }

    /**
     * @return EntitySearchResult<PaymentAppCollection>
     */
    private function searchResult(?PaymentAppEntity $app): EntitySearchResult
    {
        $entities = $app instanceof PaymentAppEntity ? [$app] : [];

        return new EntitySearchResult(
            $app instanceof PaymentAppEntity ? $app->getApiAlias() : 'payment_app',
            \count($entities),
            new PaymentAppCollection($entities),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }
}

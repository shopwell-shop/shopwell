<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\App;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\System\Payment\Exception\PaymentAppException;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * Authenticates a caller of the payment api.
 *
 * The caller sends `app_id`, `timestamp`, `nonce` and `sign` as request parameters - top level keys of
 * the json body for write requests and query parameters for read requests. The signature covers the
 * business parameters, the timestamp limits replay in time and the nonce limits it in quantity.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentAppAuthenticator
{
    /**
     * The number of seconds a request signature stays valid.
     */
    final public const TIMESTAMP_WINDOW = 300;

    /**
     * The number of seconds the secret before the last rotation still signs a request: an app hands
     * the new secret to its callers out of band, so the callers that still sign with the previous one
     * pass while they update. Only the one generation before the current secret is accepted.
     */
    final public const SECRET_ROTATION_GRACE = 86400;

    private const NONCE_PREFIX = 'shopwell.payment.nonce.';

    /**
     * @param EntityRepository<PaymentAppCollection> $paymentAppRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentAppRepository,
        private readonly PaymentSignatureService $signatureService,
        private readonly PaymentCredentialCipher $cipher,
        private readonly CacheItemPoolInterface $cache,
        private readonly LockFactory $lockFactory,
        private readonly ClockInterface $clock = new NativeClock(),
        private readonly int $timestampWindow = self::TIMESTAMP_WINDOW,
    ) {
    }

    /**
     * @param array<string, mixed> $params the complete payload including the auth parameters
     *
     * @throws PaymentException
     */
    public function authenticate(Request $request, array $params): PaymentAppEntity
    {
        $appId = $this->authValue($params, PaymentSignatureService::APP_ID);
        $timestamp = $this->authValue($params, PaymentSignatureService::TIMESTAMP);
        $nonce = $this->authValue($params, PaymentSignatureService::NONCE);
        $sign = $this->authValue($params, PaymentSignatureService::SIGN);

        if (!is_numeric($timestamp)) {
            throw PaymentAppException::expiredTimestamp(0, $this->timestampWindow);
        }

        $timestamp = (int) $timestamp;
        if (abs($this->clock->now()->getTimestamp() - $timestamp) > $this->timestampWindow) {
            throw PaymentAppException::expiredTimestamp($timestamp, $this->timestampWindow);
        }

        $app = $this->loadApp($appId);
        $secret = $this->cipher->decryptValue($app->appSecret);

        if (
            !$this->signatureService->verify($secret, $appId, $timestamp, $nonce, $request->getMethod(), $request->getPathInfo(), $params, $sign)
            && !$this->verifyWithPreviousSecret($app, $appId, $timestamp, $nonce, $request->getMethod(), $request->getPathInfo(), $params, $sign)
        ) {
            throw PaymentAppException::invalidSignature($appId);
        }

        $this->assertIpAllowed($app, $request);
        $this->assertRateLimit($app);
        $this->assertNotReplayed($appId, $nonce, $timestamp);

        return $app;
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>
     */
    public function authParametersOf(Request $request, ?array $body = null): array
    {
        $params = $body ?? [];

        foreach ($request->query->all() as $key => $value) {
            if (!\array_key_exists($key, $params)) {
                $params[(string) $key] = $value;
            }
        }

        return $params;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function authValue(array $params, string $key): string
    {
        $value = $params[$key] ?? null;

        if (!\is_scalar($value) || trim((string) $value) === '') {
            throw PaymentAppException::missingAuthParameters([$key]);
        }

        return trim((string) $value);
    }

    private function loadApp(string $appId): PaymentAppEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $appId));
        $criteria->setLimit(1);

        $app = $this->paymentAppRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        if (!$app instanceof PaymentAppEntity) {
            throw PaymentAppException::appNotFound($appId);
        }

        // the tenant of the shop is no caller: it is answered like an app that does not exist, so the
        // api does not tell anyone that this technical name is taken - the check has to come before
        // the one for `active`, otherwise a disabled platform tenant would still confirm its presence
        if ($app->type !== PaymentAppEntity::TYPE_EXTERNAL) {
            throw PaymentAppException::appNotFound($appId);
        }

        if (!$app->active) {
            throw PaymentAppException::appDisabled($appId);
        }

        return $app;
    }

    /**
     * The secret before the last rotation, accepted within the grace window after that rotation. A
     * rotation without it would lock out every caller that still signs with the old secret.
     *
     * @param array<string, mixed> $params
     */
    private function verifyWithPreviousSecret(
        PaymentAppEntity $app,
        string $appId,
        int $timestamp,
        string $nonce,
        string $method,
        string $path,
        array $params,
        string $sign
    ): bool {
        $previous = $app->previousAppSecret;
        $rotatedAt = $app->secretRotatedAt;

        if ($previous === null || $previous === '' || $rotatedAt === null) {
            return false;
        }

        if ($this->clock->now()->getTimestamp() - $rotatedAt->getTimestamp() > self::SECRET_ROTATION_GRACE) {
            return false;
        }

        return $this->signatureService->verify(
            $this->cipher->decryptValue($previous),
            $appId,
            $timestamp,
            $nonce,
            $method,
            $path,
            $params,
            $sign
        );
    }

    private function assertRateLimit(PaymentAppEntity $app): void
    {
        if ($app->rateLimit === null) {
            return;
        }
        $factory = new RateLimiterFactory([
            'id' => 'shopwell.payment.app.' . $app->id,
            'policy' => 'fixed_window',
            'limit' => $app->rateLimit,
            'interval' => '1 minute',
        ], new CacheStorage($this->cache), $this->lockFactory);
        $limit = $factory->create()->consume();
        if (!$limit->isAccepted()) {
            throw PaymentAppException::rateLimitExceeded(max(1, $limit->getRetryAfter()->getTimestamp() - (new NativeClock())->now()->getTimestamp()));
        }
    }

    private function assertNotReplayed(string $appId, string $nonce, int $timestamp): void
    {
        $key = self::NONCE_PREFIX . Hasher::hash($appId . '|' . $nonce);
        $lock = $this->lockFactory->createLock($key);
        if (!$lock->acquire()) {
            throw PaymentAppException::invalidSignature($appId);
        }

        try {
            $item = $this->cache->getItem($key);
            if ($item->isHit()) {
                throw PaymentAppException::invalidSignature($appId);
            }

            $item->set(true);
            // Timestamp admission includes the endpoint second, including future-dated requests.
            $item->expiresAt(new \DateTimeImmutable('@' . ($timestamp + $this->timestampWindow + 1)));
            if (!$this->cache->save($item)) {
                throw PaymentAppException::invalidSignature($appId);
            }
        } finally {
            $lock->release();
        }
    }

    private function assertIpAllowed(PaymentAppEntity $app, Request $request): void
    {
        $allowed = $app->allowedIps;
        if ($allowed === null || $allowed === []) {
            return;
        }

        $clientIp = $request->getClientIp();
        if ($clientIp === null || !\in_array($clientIp, $allowed, true)) {
            throw PaymentAppException::invalidSignature($app->technicalName);
        }
    }
}

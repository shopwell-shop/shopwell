<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Service;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppEntity;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;

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

    private const NONCE_PREFIX = 'shopwell.payment.nonce.';

    /**
     * @param EntityRepository<PaymentAppCollection> $paymentAppRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentAppRepository,
        private readonly PaymentSignatureService $signatureService,
        private readonly PaymentCredentialCipher $cipher,
        private readonly CacheItemPoolInterface $cache,
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
            throw PaymentException::expiredTimestamp(0, $this->timestampWindow);
        }

        $timestamp = (int) $timestamp;
        if (abs($this->clock->now()->getTimestamp() - $timestamp) > $this->timestampWindow) {
            throw PaymentException::expiredTimestamp($timestamp, $this->timestampWindow);
        }

        $app = $this->loadApp($appId);
        $secret = $this->cipher->decryptValue($app->appSecret);

        if (!$this->signatureService->verify(
            $secret,
            $appId,
            $timestamp,
            $nonce,
            $request->getMethod(),
            $request->getPathInfo(),
            $params,
            $sign
        )) {
            throw PaymentException::invalidSignature($appId);
        }

        $this->assertNotReplayed($appId, $nonce);
        $this->assertIpAllowed($app, $request);

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
            throw PaymentException::missingAuthParameters([$key]);
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
            throw PaymentException::appNotFound($appId);
        }

        if (!$app->active) {
            throw PaymentException::appDisabled($appId);
        }

        return $app;
    }

    private function assertNotReplayed(string $appId, string $nonce): void
    {
        $item = $this->cache->getItem(self::NONCE_PREFIX . Hasher::hash($appId . '|' . $nonce));

        if ($item->isHit()) {
            throw PaymentException::invalidSignature($appId);
        }

        $item->set(true);
        $item->expiresAfter($this->timestampWindow);
        $this->cache->save($item);
    }

    private function assertIpAllowed(PaymentAppEntity $app, Request $request): void
    {
        $allowed = $app->allowedIps;
        if ($allowed === null || $allowed === []) {
            return;
        }

        $clientIp = $request->getClientIp();
        if ($clientIp === null || !\in_array($clientIp, $allowed, true)) {
            throw PaymentException::invalidSignature($app->technicalName);
        }
    }
}

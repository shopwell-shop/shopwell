<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sso\Service;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\SsoException;
use Shopwell\Core\Framework\Util\Hasher;

/**
 * Carries a successful third party authorization from the callback of a platform to the token
 * endpoint of the administration.
 *
 * The browser is redirected to the administration with the code in the url, so the code is short
 * lived and can only be used once: consuming it removes it from the cache, a second request with
 * the same code fails. The code itself is never stored, only a hash of it is used as the cache
 * key, so the cache does not leak codes that are still valid.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class OAuth2LoginCodeService
{
    private const CACHE_PREFIX = 'shopwell.oauth2.login.code.';

    /**
     * Long enough for the redirect of the browser, short enough that a leaked url is worthless.
     */
    private const CODE_TTL = 60;

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return string the code that identifies the admin user for one token request
     */
    public function create(string $userId): string
    {
        $code = bin2hex(random_bytes(32));

        $item = $this->cache->getItem($this->key($code));
        $item->set(['userId' => $userId, 'expiry' => $this->clock->now()->getTimestamp() + self::CODE_TTL]);
        $item->expiresAfter(self::CODE_TTL);
        $this->cache->save($item);

        return $code;
    }

    /**
     * @throws SsoException when the code is unknown, already used or expired
     */
    public function consume(string $code): string
    {
        $key = $this->key($code);
        $item = $this->cache->getItem($key);

        $payload = $item->isHit() ? $item->get() : null;

        // remove it before validating, so a code is burned by the first request that presents it
        $this->cache->deleteItem($key);

        if (!\is_array($payload)
            || !\is_string($payload['userId'] ?? null)
            || !\is_int($payload['expiry'] ?? null)
            || $payload['expiry'] < $this->clock->now()->getTimestamp()
        ) {
            throw SsoException::oauth2InvalidLoginCode();
        }

        return $payload['userId'];
    }

    private function key(string $code): string
    {
        return self::CACHE_PREFIX . Hasher::hash($code);
    }
}

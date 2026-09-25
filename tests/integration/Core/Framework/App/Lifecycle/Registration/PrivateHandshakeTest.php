<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\App\Lifecycle\Registration;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\Lifecycle\Registration\PrivateHandshake;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Util\Random;
use Shopwell\Core\Kernel;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[Package('framework')]
class PrivateHandshakeTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testUrlContainsAllNecessaryElements(): void
    {
        $shopUrl = 'test.shop.com';
        $secret = 's3cr3t';
        $appEndpoint = 'https://test.com/install';
        $shopId = Random::getAlphanumericString(12);

        $handshake = new PrivateHandshake($shopUrl, $secret, $appEndpoint, '', $shopId, Kernel::SHOPWELL_FALLBACK_VERSION, new NativeClock());

        $request = $handshake->assembleRequest();
        static::assertStringStartsWith($appEndpoint, (string) $request->getUri());

        $queryParams = [];
        parse_str($request->getUri()->getQuery(), $queryParams);

        static::assertArrayHasKey('shop-url', $queryParams);
        static::assertSame(urlencode($shopUrl), $queryParams['shop-url']);

        static::assertArrayHasKey('shop-id', $queryParams);
        static::assertSame($shopId, $queryParams['shop-id']);

        static::assertArrayHasKey('timestamp', $queryParams);
        static::assertIsString($queryParams['timestamp']);
        static::assertNotEmpty($queryParams['timestamp']);

        static::assertTrue($request->hasHeader('shopwell-app-signature'));
        static::assertSame(
            hash_hmac('sha256', $request->getUri()->getQuery(), $secret),
            $request->getHeaderLine('shopwell-app-signature')
        );

        static::assertNotEmpty($request->getHeaderLine('sw-version'));
    }

    public function testAppProof(): void
    {
        $shopUrl = 'test.shop.com';
        $secret = 'stuff';
        $appEndpoint = 'https://test.com/install';
        $appName = 'testapp';
        $shopId = Random::getAlphanumericString(12);

        $handshake = new PrivateHandshake($shopUrl, $secret, $appEndpoint, $appName, $shopId, Kernel::SHOPWELL_FALLBACK_VERSION, new NativeClock());

        $appProof = $handshake->fetchAppProof();

        static::assertSame(hash_hmac('sha256', $shopId . $shopUrl . $appName, $secret), $appProof);
    }
}

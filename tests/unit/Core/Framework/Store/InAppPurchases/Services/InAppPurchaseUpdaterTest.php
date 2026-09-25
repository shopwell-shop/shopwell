<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Store\InAppPurchases\Services;

use Doctrine\DBAL\Connection;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\JWT\JWTDecoder;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Authentication\AbstractStoreRequestOptionsProvider;
use Shopwell\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopwell\Core\Framework\Store\InAppPurchase;
use Shopwell\Core\Framework\Store\InAppPurchase\Event\InAppPurchaseChangedEvent;
use Shopwell\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseProvider;
use Shopwell\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseUpdater;
use Shopwell\Core\Framework\Store\InAppPurchase\Services\KeyFetcher;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(InAppPurchaseUpdater::class)]
class InAppPurchaseUpdaterTest extends TestCase
{
    public function testUpdateActiveInAppPurchases(): void
    {
        $jwt = file_get_contents(__DIR__ . '../../../_fixtures/jwt.json');
        static::assertIsString($jwt);

        $jwks = file_get_contents(__DIR__ . '/../../../JWT/_fixtures/valid-jwks.json');
        static::assertIsString($jwks);

        $client = $this->createMock(ClientInterface::class);

        $client->expects($this->once())
            ->method('request')
            ->with('GET', 'https://test.com', ['query' => ['a'], 'headers' => ['b']])
            ->willReturn(new Response(200, [], $jwt));

        $systemConfig = new StaticSystemConfigService([
            'core.store.licenseHost' => 'example.com',
            InAppPurchaseProvider::CONFIG_STORE_IAP_KEY => $jwt,
            KeyFetcher::CORE_STORE_JWKS => $jwks,
        ]);

        $optionsProvider = $this->createMock(AbstractStoreRequestOptionsProvider::class);
        $optionsProvider->expects($this->once())
            ->method('getDefaultQueryParameters')
            ->willReturn(['a']);
        $optionsProvider->expects($this->once())
            ->method('getAuthenticationHeader')
            ->willReturn(['b']);

        $context = Context::createDefaultContext();
        $appId = Uuid::randomHex();
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::equalTo(new InAppPurchaseChangedEvent('TestApp', '["test","test2"]', $appId, $context)));

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn(['TestApp' => $appId]);

        $iap = new InAppPurchase(
            new InAppPurchaseProvider(
                $systemConfig,
                new JWTDecoder(),
                new KeyFetcher(
                    static::createStub(ClientInterface::class),
                    static::createStub(StoreRequestOptionsProvider::class),
                    $systemConfig,
                    static::createStub(LoggerInterface::class)
                ),
                static::createStub(LoggerInterface::class),
                new NativeClock()
            )
        );

        $service = new InAppPurchaseUpdater(
            $client,
            $systemConfig,
            'https://test.com',
            $optionsProvider,
            $iap,
            $eventDispatcher,
            $connection,
            static::createStub(LoggerInterface::class)
        );
        $service->update($context);

        static::assertSame($jwt, $systemConfig->get('core.store.iapKey'));
    }

    public function testUpdateActiveInAppPurchasesWithoutAuthentication(): void
    {
        $jwt = file_get_contents(__DIR__ . '../../../_fixtures/jwt.json');
        static::assertIsString($jwt);

        $jwks = file_get_contents(__DIR__ . '/../../../JWT/_fixtures/valid-jwks.json');
        static::assertIsString($jwks);

        $client = $this->createMock(ClientInterface::class);

        $client->expects($this->never())
            ->method('request');

        $systemConfig = new StaticSystemConfigService([
            'core.store.licenseHost' => 'example.com',
            InAppPurchaseProvider::CONFIG_STORE_IAP_KEY => $jwt,
            KeyFetcher::CORE_STORE_JWKS => $jwks,
        ]);

        $optionsProvider = $this->createMock(AbstractStoreRequestOptionsProvider::class);
        $optionsProvider->expects($this->never())
            ->method('getDefaultQueryParameters');
        $optionsProvider->expects($this->once())
            ->method('getAuthenticationHeader')
            ->willReturn([]);

        $context = Context::createDefaultContext();
        $appId = Uuid::randomHex();
        $eventDispatcher = static::createStub(EventDispatcherInterface::class);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn(['TestApp' => $appId]);

        $iap = new InAppPurchase(
            new InAppPurchaseProvider(
                $systemConfig,
                new JWTDecoder(),
                new KeyFetcher(
                    static::createStub(ClientInterface::class),
                    static::createStub(StoreRequestOptionsProvider::class),
                    $systemConfig,
                    static::createStub(LoggerInterface::class)
                ),
                static::createStub(LoggerInterface::class),
                new NativeClock()
            )
        );

        $service = new InAppPurchaseUpdater(
            $client,
            $systemConfig,
            'https://test.com',
            $optionsProvider,
            $iap,
            $eventDispatcher,
            $connection,
            static::createStub(LoggerInterface::class)
        );
        $service->update($context);

        static::assertSame($jwt, $systemConfig->get('core.store.iapKey'));
    }
}

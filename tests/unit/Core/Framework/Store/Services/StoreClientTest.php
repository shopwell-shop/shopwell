<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Store\Services;

use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Authentication\AbstractStoreRequestOptionsProvider;
use Shopwell\Core\Framework\Store\Event\ShopwellAccountLogoutEvent;
use Shopwell\Core\Framework\Store\Services\ExtensionLoader;
use Shopwell\Core\Framework\Store\Services\InstanceService;
use Shopwell\Core\Framework\Store\Services\StoreClient;
use Shopwell\Core\Framework\Store\Services\StoreService;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(StoreClient::class)]
class StoreClientTest extends TestCase
{
    public function testLogoutRemovesStoreTokenAndDispatchesLogoutEvent(): void
    {
        $context = new Context(new AdminApiSource(Uuid::randomHex()));

        $storeService = $this->createMock(StoreService::class);
        $storeService->expects($this->once())
            ->method('removeStoreToken')
            ->with($context);

        $eventDispatcher = new CollectingEventDispatcher();

        $storeClient = new StoreClient(
            [],
            $storeService,
            static::createStub(SystemConfigService::class),
            static::createStub(AbstractStoreRequestOptionsProvider::class),
            static::createStub(ExtensionLoader::class),
            static::createStub(ClientInterface::class),
            static::createStub(InstanceService::class),
            new RequestStack(),
            static::createStub(CacheInterface::class),
            $eventDispatcher,
        );

        $storeClient->logout($context);

        static::assertCount(1, $eventDispatcher->getEvents());
        static::assertInstanceOf(ShopwellAccountLogoutEvent::class, $eventDispatcher->getEvents()[0]);
    }
}

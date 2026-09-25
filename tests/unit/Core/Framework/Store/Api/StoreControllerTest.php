<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Store\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Api\StoreController;
use Shopwell\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopwell\Core\Framework\Store\Services\StoreClient;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(StoreController::class)]
class StoreControllerTest extends TestCase
{
    public function testLogoutDelegatesToStoreClient(): void
    {
        $context = new Context(new AdminApiSource(Uuid::randomHex()));

        $storeClient = $this->createMock(StoreClient::class);
        $storeClient->expects($this->once())
            ->method('logout')
            ->with($context);

        $userRepository = static::createStub(EntityRepository::class);

        $storeController = new StoreController(
            $storeClient,
            $userRepository,
            static::createStub(AbstractExtensionDataProvider::class),
        );

        $response = $storeController->logout($context);

        static::assertSame(200, $response->getStatusCode());
    }
}

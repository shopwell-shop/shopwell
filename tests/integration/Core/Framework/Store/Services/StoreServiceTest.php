<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Store\Services;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Services\StoreService;
use Shopwell\Core\Framework\Store\Struct\AccessTokenStruct;
use Shopwell\Core\Framework\Store\Struct\ShopUserTokenStruct;
use Shopwell\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\System\User\UserEntity;

/**
 * @internal
 */
#[Package('checkout')]
class StoreServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    private StoreService $storeService;

    protected function setUp(): void
    {
        $this->storeService = static::getContainer()->get(StoreService::class);
    }

    public function testUpdateStoreToken(): void
    {
        $adminStoreContext = $this->createAdminStoreContext();

        $newToken = 'updated-store-token';
        $accessTokenStruct = new AccessTokenStruct(
            new ShopUserTokenStruct(
                $newToken,
                new \DateTimeImmutable()
            )
        );

        $this->storeService->updateStoreToken(
            $adminStoreContext,
            $accessTokenStruct
        );

        $user = $this->fetchUser($adminStoreContext);
        static::assertSame('updated-store-token', $user?->getStoreToken());
    }

    public function testRemoveStoreToken(): void
    {
        $adminStoreContext = $this->createAdminStoreContext();

        $accessTokenStruct = new AccessTokenStruct(
            new ShopUserTokenStruct('store-token', new \DateTimeImmutable())
        );

        $this->storeService->updateStoreToken(
            $adminStoreContext,
            $accessTokenStruct
        );
        $this->storeService->removeStoreToken($adminStoreContext);

        $user = $this->fetchUser($adminStoreContext);
        static::assertNotNull($user);
        static::assertNull($user->getStoreToken());
    }

    private function fetchUser(Context $context): ?UserEntity
    {
        /** @var AdminApiSource $adminSource */
        $adminSource = $context->getSource();
        /** @var string $userId */
        $userId = $adminSource->getUserId();
        $criteria = new Criteria([$userId]);

        return $this->getUserRepository()->search($criteria, $context)->getEntities()->first();
    }
}

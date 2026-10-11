<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Store\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\System\User\UserEntity;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class LicenseHostChangedSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testDeletesShopSecretAndLogsOutAllUsers(): void
    {
        $context = Context::createDefaultContext();

        $systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $systemConfigService->set('core.store.licenseHost', 'host');
        $systemConfigService->set('core.store.shopSecret', 'shop-s3cr3t');

        /** @var EntityRepository<UserCollection> $userRepository */
        $userRepository = static::getContainer()->get('user.repository');

        $user = $userRepository->search(new Criteria(), $context)->getEntities()->first();
        static::assertInstanceOf(UserEntity::class, $user);

        // We create two new admin users
        $expectedAdminUsersCount = \count($this->fetchAllAdminUsers()) + 2;

        $userRepository->create([
            [
                'localeId' => $user->getLocaleId(),
                'username' => 'admin2',
                'password' => TestDefaults::HASHED_PASSWORD,
                'name' => 'admin2 admin2',
                'email' => 'admin2@shopwell.cn',
                'storeToken' => null,
            ],
            [
                'localeId' => $user->getLocaleId(),
                'username' => 'admin3',
                'password' => TestDefaults::HASHED_PASSWORD,
                'name' => 'admin3 admin3',
                'email' => 'admin3@shopwell.cn',
                'storeToken' => null,
            ],
        ], $context);

        $systemConfigService->set('core.store.licenseHost', 'otherhost');
        $adminUsers = $this->fetchAllAdminUsers();

        static::assertCount($expectedAdminUsersCount, $adminUsers);
        foreach ($adminUsers as $adminUser) {
            static::assertNull($adminUser['store_token']);
        }

        static::assertNull($systemConfigService->get('core.store.shopSecret'));
    }

    /**
     * @return array<array<string, string>>
     */
    private function fetchAllAdminUsers(): array
    {
        return static::getContainer()->get(Connection::class)->executeQuery(
            'SELECT * FROM user'
        )->fetchAllAssociative();
    }
}

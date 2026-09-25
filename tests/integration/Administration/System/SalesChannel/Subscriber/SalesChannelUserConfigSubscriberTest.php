<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Administration\System\SalesChannel\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Administration\System\SalesChannel\Subscriber\SalesChannelUserConfigSubscriber;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\User\Aggregate\UserConfig\UserConfigCollection;

/**
 * @internal
 */
#[Package('discovery')]
class SalesChannelUserConfigSubscriberTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    public function testDeleteWillRemoveUserConfigs(): void
    {
        $admin = TestUser::createNewTestUser(static::getContainer()->get(Connection::class), ['product:read']);
        $context = Context::createDefaultContext();

        $salesChannelId1 = Uuid::randomHex();
        $salesChannelId2 = Uuid::randomHex();

        /** @var EntityRepository<UserConfigCollection> $userConfigRepository */
        $userConfigRepository = static::getContainer()->get('user_config.repository');
        $userConfigId = Uuid::randomHex();
        $userConfigRepository->create([
            [
                'id' => $userConfigId,
                'userId' => $admin->getUserId(),
                'key' => SalesChannelUserConfigSubscriber::CONFIG_KEY,
                'value' => [$salesChannelId1, $salesChannelId2],
                'createdAt' => new \DateTime(),
            ],
        ], $context);

        $search = $userConfigRepository->search(new Criteria([$userConfigId]), $context)
            ->getEntities()
            ->first();

        static::assertNotNull($search);
        static::assertIsArray($search->getValue());
        static::assertCount(2, $search->getValue());

        $this->createSalesChannel(['id' => $salesChannelId1]);
        $this->createSalesChannel(['id' => $salesChannelId2]);

        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');
        $salesChannelRepository->delete([['id' => $salesChannelId1], ['id' => $salesChannelId2]], $context);

        $search = $userConfigRepository->search(new Criteria([$userConfigId]), $context)
            ->getEntities()
            ->first();

        static::assertNotNull($search);
        static::assertIsArray($search->getValue());
        static::assertCount(0, $search->getValue());
    }
}

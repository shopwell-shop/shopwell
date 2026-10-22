<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\User;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Position\PositionCollection;
use Shopwell\Core\System\User\UserCollection;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[Group('dictionary')]
class UserOrganizationUnitMappingTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testUserOrganizationUnitAndPositionMappingIsIdempotent(): void
    {
        $context = Context::createDefaultContext();
        $userId = $this->createUser($context, 'mapping-user');

        $orgUnitA = $this->createOrganizationUnit('unit-a', null);
        $orgUnitB = $this->createOrganizationUnit('unit-b', $orgUnitA);
        $positionId = $this->createPosition($context);

        /** @var EntityRepository<UserCollection> $userRepository */
        $userRepository = static::getContainer()->get('user.repository');

        $payload = [
            'id' => $userId,
            'organizationUnits' => [['id' => $orgUnitA], ['id' => $orgUnitB]],
            'positions' => [['id' => $positionId]],
        ];

        $userRepository->update([$payload], $context);
        $userRepository->update([$payload], $context);

        $connection = static::getContainer()->get(Connection::class);

        $mappingCount = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM user_organization_unit WHERE user_id = :userId',
            ['userId' => Uuid::fromHexToBytes($userId)]
        );
        static::assertSame(2, $mappingCount, 'duplicate assignments must not create duplicate mapping rows');

        $positionCount = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM user_position WHERE user_id = :userId',
            ['userId' => Uuid::fromHexToBytes($userId)]
        );
        static::assertSame(1, $positionCount);
    }

    public function testWritingPrimaryUnitResetsOtherPrimaryMappings(): void
    {
        $context = Context::createDefaultContext();
        $userId = $this->createUser($context, 'primary-user');

        $orgUnitA = $this->createOrganizationUnit('primary-a', null);
        $orgUnitB = $this->createOrganizationUnit('primary-b', null);

        /** @var EntityRepository<UserCollection> $mappingRepository */
        $mappingRepository = static::getContainer()->get('user_organization_unit.repository');

        $mappingRepository->create([
            ['userId' => $userId, 'organizationUnitId' => $orgUnitA, 'isPrimary' => true],
            ['userId' => $userId, 'organizationUnitId' => $orgUnitB, 'isPrimary' => false],
        ], $context);

        $connection = static::getContainer()->get(Connection::class);
        $primaryCount = static fn (): int => (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM user_organization_unit WHERE user_id = :userId AND is_primary = 1',
            ['userId' => Uuid::fromHexToBytes($userId)]
        );

        static::assertSame(1, $primaryCount(), 'exactly one primary unit');

        // switch primary to unit B
        $mappingRepository->upsert([
            ['userId' => $userId, 'organizationUnitId' => $orgUnitB, 'isPrimary' => true],
        ], $context);

        $primaryUnit = $connection->fetchOne(
            'SELECT LOWER(HEX(organization_unit_id)) FROM user_organization_unit WHERE user_id = :userId AND is_primary = 1',
            ['userId' => Uuid::fromHexToBytes($userId)]
        );
        static::assertSame($orgUnitB, $primaryUnit, 'primary flag must move to the newly assigned unit');
        static::assertSame(1, $primaryCount(), 'previous primary must be reset');
    }

    public function testDeletingUnitRemovesMapping(): void
    {
        $context = Context::createDefaultContext();
        $userId = $this->createUser($context, 'delete-user');

        $orgUnitA = $this->createOrganizationUnit('delete-a', null);

        /** @var EntityRepository<UserCollection> $userRepository */
        $userRepository = static::getContainer()->get('user.repository');
        $userRepository->update([[
            'id' => $userId,
            'organizationUnits' => [['id' => $orgUnitA]],
        ]], $context);

        $connection = static::getContainer()->get(Connection::class);
        $connection->delete('organization_unit', ['id' => Uuid::fromHexToBytes($orgUnitA)]);

        $mappingCount = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM user_organization_unit WHERE user_id = :userId',
            ['userId' => Uuid::fromHexToBytes($userId)]
        );
        static::assertSame(0, $mappingCount, 'mapping rows must be removed with the unit');
    }

    private function createUser(Context $context, string $key): string
    {
        $id = Uuid::randomHex();

        /** @var EntityRepository<UserCollection> $userRepository */
        $userRepository = static::getContainer()->get('user.repository');
        $userRepository->create([[
            'id' => $id,
            'localeId' => $this->getLocaleIdOfSystemLanguage(),
            'username' => $key . '-' . Uuid::randomHex(),
            'password' => 'shopwell',
            'name' => $key,
            'email' => $key . '@example.com',
            'timeZone' => 'Asia/Shanghai',
            'active' => true,
        ]], $context);

        return $id;
    }

    private function createOrganizationUnit(string $name, ?string $parentId): string
    {
        $connection = static::getContainer()->get(Connection::class);

        $orgId = Uuid::randomHex();
        $connection->insert('organization', [
            'id' => Uuid::fromHexToBytes($orgId),
            'active' => 1,
            'created_at' => '2026-01-01 00:00:00.000',
        ]);
        $connection->insert('organization_translation', [
            'organization_id' => Uuid::fromHexToBytes($orgId),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'name' => $name . ' org',
            'created_at' => '2026-01-01 00:00:00.000',
        ]);

        $unitId = Uuid::randomHex();
        $connection->insert('organization_unit', [
            'id' => Uuid::fromHexToBytes($unitId),
            'organization_id' => Uuid::fromHexToBytes($orgId),
            'parent_id' => $parentId !== null ? Uuid::fromHexToBytes($parentId) : null,
            'level' => $parentId !== null ? 2 : 1,
            'active' => 1,
            'created_at' => '2026-01-01 00:00:00.000',
        ]);
        $connection->insert('organization_unit_translation', [
            'organization_unit_id' => Uuid::fromHexToBytes($unitId),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'name' => $name,
            'created_at' => '2026-01-01 00:00:00.000',
        ]);

        return $unitId;
    }

    private function createPosition(Context $context): string
    {
        $id = Uuid::randomHex();

        /** @var EntityRepository<PositionCollection> $repository */
        $repository = static::getContainer()->get('position.repository');
        $repository->create([[
            'id' => $id,
            'position' => 1,
            'active' => true,
            'name' => 'Developer',
        ]], $context);

        return $id;
    }
}

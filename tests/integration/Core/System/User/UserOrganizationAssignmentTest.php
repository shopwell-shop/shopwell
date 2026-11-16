<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\User;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Sync\SyncBehavior;
use Shopwell\Core\Framework\Api\Sync\SyncOperation;
use Shopwell\Core\Framework\Api\Sync\SyncService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Exception\InvalidForeignKeyReferenceException;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Organization\OrganizationDefaults;
use Shopwell\Core\System\User\Aggregate\UserOrganizationUnit\UserOrganizationUnitEntity;
use Shopwell\Core\System\User\Aggregate\UserPosition\UserPositionEntity;
use Shopwell\Core\System\User\UserEntity;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
class UserOrganizationAssignmentTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testNewUserHasOnePrimaryDepartmentAndPosition(): void
    {
        $userId = $this->createUser();
        $criteria = (new Criteria([$userId]))->addAssociation('organizationUnitMappings')->addAssociation('positionMappings');
        $user = static::getContainer()->get('user.repository')->search($criteria, Context::createDefaultContext())->first();

        static::assertInstanceOf(UserEntity::class, $user);
        $departments = $user->getOrganizationUnitMappings();
        $positions = $user->getPositionMappings();
        static::assertNotNull($departments);
        static::assertNotNull($positions);
        static::assertCount(1, $departments);
        static::assertCount(1, $positions);
        $department = $departments->first();
        $position = $positions->first();
        static::assertInstanceOf(UserOrganizationUnitEntity::class, $department);
        static::assertInstanceOf(UserPositionEntity::class, $position);
        static::assertSame(OrganizationDefaults::UNIT, $department->getOrganizationUnitId());
        static::assertTrue($department->getIsPrimary());
        static::assertSame(OrganizationDefaults::POSITION, $position->getPositionId());
        static::assertSame(OrganizationDefaults::UNIT, $position->getOrganizationUnitId());
        static::assertTrue($position->getIsPrimary());
        static::assertSame([OrganizationDefaults::UNIT], $user->getOrganizationUnitIds());
        static::assertSame([OrganizationDefaults::POSITION], $user->getPositionIds());
    }

    public function testSamePositionCanBeAssignedInDifferentDepartments(): void
    {
        $userId = $this->createUser();
        $unitId = $this->createUnit(OrganizationDefaults::ORGANIZATION);
        $context = Context::createDefaultContext();
        static::getContainer()->get('user_organization_unit.repository')->create([[
            'userId' => $userId, 'organizationUnitId' => $unitId,
        ]], $context);
        static::getContainer()->get('user_position.repository')->create([[
            'userId' => $userId, 'positionId' => OrganizationDefaults::POSITION, 'organizationUnitId' => $unitId,
        ]], $context);

        static::assertSame(2, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM user_position WHERE user_id = UNHEX(?)', [$userId]));
        static::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM user_position WHERE user_id = UNHEX(?) AND is_primary = 1', [$userId]));
    }

    public function testSupervisorIsAnExplicitMembershipAndLeadersAreOrdered(): void
    {
        $employee = $this->createUser();
        $manager = $this->createUser();
        $employeeMembership = $this->membershipId($employee);
        $managerMembership = $this->membershipId($manager);
        static::getContainer()->get('user_organization_unit.repository')->update([
            ['id' => $employeeMembership, 'supervisorMembershipId' => $managerMembership],
            ['id' => $managerMembership, 'isLeader' => true, 'leaderPosition' => 10],
        ], Context::createDefaultContext());

        $membership = static::getContainer()->get('user_organization_unit.repository')
            ->search((new Criteria([$employeeMembership]))->addAssociation('supervisorMembership.user'), Context::createDefaultContext())->first();
        static::assertInstanceOf(UserOrganizationUnitEntity::class, $membership);
        static::assertSame($manager, $membership->getSupervisorMembership()?->getUserId());
        static::assertSame([$managerMembership], $this->connection()->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) FROM user_organization_unit WHERE organization_unit_id = UNHEX(?) AND is_leader = 1 ORDER BY leader_position, id',
            [OrganizationDefaults::UNIT],
        ));
    }

    public function testSupervisorCycleIsRejectedAndRolledBack(): void
    {
        $first = $this->membershipId($this->createUser());
        $second = $this->membershipId($this->createUser());
        $repository = static::getContainer()->get('user_organization_unit.repository');
        $repository->update([['id' => $first, 'supervisorMembershipId' => $second]], Context::createDefaultContext());

        try {
            $repository->update([['id' => $second, 'supervisorMembershipId' => $first]], Context::createDefaultContext());
            static::fail('A supervisor cycle must be rejected.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('USER__SUPERVISOR_CYCLE', json_encode(iterator_to_array($exception->getErrors()), \JSON_THROW_ON_ERROR));
        }

        static::assertNull($this->connection()->fetchOne('SELECT supervisor_membership_id FROM user_organization_unit WHERE id = UNHEX(?)', [$second]));
    }

    public function testSupervisorFromAnotherOrganizationIsRejected(): void
    {
        $employee = $this->membershipId($this->createUser());
        $manager = $this->createUser();
        $organizationId = Uuid::randomHex();
        static::getContainer()->get('organization.repository')->create([['id' => $organizationId, 'name' => 'Other company', 'active' => true]], Context::createDefaultContext());
        $unitId = $this->createUnit($organizationId);
        $managerMembership = Uuid::randomHex();
        $repository = static::getContainer()->get('user_organization_unit.repository');
        $repository->create([['id' => $managerMembership, 'userId' => $manager, 'organizationUnitId' => $unitId]], Context::createDefaultContext());

        $this->expectException(WriteException::class);
        $repository->update([['id' => $employee, 'supervisorMembershipId' => $managerMembership]], Context::createDefaultContext());
    }

    public function testLastPrimaryDepartmentCannotBeCleared(): void
    {
        $membershipId = $this->membershipId($this->createUser());
        $this->expectException(WriteException::class);
        static::getContainer()->get('user_organization_unit.repository')->update([[
            'id' => $membershipId, 'isPrimary' => false,
        ]], Context::createDefaultContext());
    }

    public function testDepartmentAndPrimaryPositionCanBeReplacedAtomically(): void
    {
        $userId = $this->createUser();
        $oldMembershipId = $this->membershipId($userId);
        $oldPositionId = $this->connection()->fetchOne('SELECT LOWER(HEX(id)) FROM user_position WHERE user_id = UNHEX(?)', [$userId]);
        $newUnitId = $this->createUnit(OrganizationDefaults::ORGANIZATION);
        $newMembershipId = Uuid::randomHex();
        $newPositionId = Uuid::randomHex();

        static::getContainer()->get(SyncService::class)->sync([
            new SyncOperation('departments', 'user_organization_unit', 'upsert', [[
                'id' => $newMembershipId, 'userId' => $userId, 'organizationUnitId' => $newUnitId, 'isPrimary' => true,
            ]]),
            new SyncOperation('positions', 'user_position', 'upsert', [[
                'id' => $newPositionId, 'userId' => $userId, 'positionId' => OrganizationDefaults::POSITION,
                'organizationUnitId' => $newUnitId, 'isPrimary' => true,
            ]]),
            new SyncOperation('removedPositions', 'user_position', 'delete', [['id' => $oldPositionId]]),
            new SyncOperation('removedDepartments', 'user_organization_unit', 'delete', [['id' => $oldMembershipId]]),
        ], Context::createDefaultContext(), new SyncBehavior());

        static::assertSame($newMembershipId, $this->membershipId($userId));
        static::assertSame([$newPositionId], $this->connection()->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM user_position WHERE user_id = UNHEX(?)', [$userId]));
    }

    public function testPartialPrimarySwitchSuppliesTheDepartmentForANewPosition(): void
    {
        $userId = $this->createUser();
        $unitId = $this->createUnit(OrganizationDefaults::ORGANIZATION);
        $membershipId = Uuid::randomHex();
        static::getContainer()->get('user_organization_unit.repository')->create([[
            'id' => $membershipId, 'userId' => $userId, 'organizationUnitId' => $unitId,
        ]], Context::createDefaultContext());
        $positionId = Uuid::randomHex();
        static::getContainer()->get('position.repository')->create([['id' => $positionId, 'name' => 'New position', 'active' => true]], Context::createDefaultContext());
        $assignmentId = Uuid::randomHex();

        static::getContainer()->get(SyncService::class)->sync([
            new SyncOperation('departments', 'user_organization_unit', 'upsert', [['id' => $membershipId, 'isPrimary' => true]]),
            new SyncOperation('positions', 'user_position', 'upsert', [['id' => $assignmentId, 'userId' => $userId, 'positionId' => $positionId]]),
        ], Context::createDefaultContext(), new SyncBehavior());

        static::assertSame($unitId, $this->connection()->fetchOne('SELECT LOWER(HEX(organization_unit_id)) FROM user_position WHERE id = UNHEX(?)', [$assignmentId]));
    }

    public function testPositionCannotBelongToANonMemberDepartment(): void
    {
        $userId = $this->createUser();
        $unitId = $this->createUnit(OrganizationDefaults::ORGANIZATION);
        $this->expectException(InvalidForeignKeyReferenceException::class);
        static::getContainer()->get('user_position.repository')->create([[
            'userId' => $userId, 'positionId' => OrganizationDefaults::POSITION, 'organizationUnitId' => $unitId,
        ]], Context::createDefaultContext());
    }

    public function testPrimaryPositionCannotBeCleared(): void
    {
        $userId = $this->createUser();
        $positionId = $this->connection()->fetchOne('SELECT LOWER(HEX(id)) FROM user_position WHERE user_id = UNHEX(?)', [$userId]);
        $this->expectException(WriteException::class);
        static::getContainer()->get('user_position.repository')->update([[
            'id' => $positionId, 'isPrimary' => false,
        ]], Context::createDefaultContext());
    }

    public function testDeletingUserAlsoDeletesTheirAssignments(): void
    {
        $userId = $this->createUser();
        static::getContainer()->get('user.repository')->delete([['id' => $userId]], Context::createDefaultContext());

        static::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM user_organization_unit WHERE user_id = UNHEX(?)', [$userId]));
        static::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM user_position WHERE user_id = UNHEX(?)', [$userId]));
    }

    private function createUser(): string
    {
        $id = Uuid::randomHex();
        static::getContainer()->get('user.repository')->create([[
            'id' => $id,
            'localeId' => Uuid::fromBytesToHex($this->connection()->fetchOne('SELECT id FROM locale LIMIT 1')),
            'username' => $id,
            'password' => 'shopwell',
            'name' => 'Organization assignment test',
            'email' => $id . '@example.com',
            'active' => true,
        ]], Context::createDefaultContext());

        return $id;
    }

    private function createUnit(string $organizationId): string
    {
        $id = Uuid::randomHex();
        static::getContainer()->get('organization_unit.repository')->create([[
            'id' => $id, 'organizationId' => $organizationId, 'name' => 'Test department', 'active' => true,
        ]], Context::createDefaultContext());

        return $id;
    }

    private function membershipId(string $userId): string
    {
        $id = $this->connection()->fetchOne('SELECT LOWER(HEX(id)) FROM user_organization_unit WHERE user_id = UNHEX(?) AND is_primary = 1', [$userId]);
        static::assertIsString($id);

        return $id;
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}

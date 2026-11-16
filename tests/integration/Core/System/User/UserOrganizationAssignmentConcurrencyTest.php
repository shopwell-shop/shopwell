<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\User;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\RetryableException;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Organization\OrganizationDefaults;
use Shopwell\Core\Test\Integration\EventDispatcher\EventHookDispatcher;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
class UserOrganizationAssignmentConcurrencyTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private Connection $otherConnection;

    /**
     * @var list<string>
     */
    private array $userIds = [];

    /**
     * @var list<string>
     */
    private array $organizationIds = [];

    /**
     * @var list<string>
     */
    private array $positionIds = [];

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->otherConnection = DriverManager::getConnection($this->connection->getParams());
    }

    protected function tearDown(): void
    {
        EventHookDispatcher::resetAll();
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        $this->otherConnection->close();
        $context = Context::createDefaultContext();
        foreach ($this->userIds as $id) {
            static::getContainer()->get('user.repository')->delete([['id' => $id]], $context);
        }
        foreach ($this->organizationIds as $id) {
            static::getContainer()->get('organization.repository')->delete([['id' => $id]], $context);
        }
        foreach ($this->positionIds as $id) {
            static::getContainer()->get('position.repository')->delete([['id' => $id]], $context);
        }
        parent::tearDown();
    }

    public function testSupervisorValidationUsesCurrentOrganizationDespiteAnOlderSnapshot(): void
    {
        [$firstOrganization, $firstUnit] = $this->createOrganization();
        [, $secondUnit] = $this->createOrganization();
        $employee = $this->createUser();
        $manager = $this->createUser();
        $employeeMembership = Uuid::randomHex();
        $managerMembership = Uuid::randomHex();
        $repository = static::getContainer()->get('user_organization_unit.repository');
        $repository->create([
            ['id' => $employeeMembership, 'userId' => $employee, 'organizationUnitId' => $firstUnit],
            ['id' => $managerMembership, 'userId' => $manager, 'organizationUnitId' => $firstUnit],
        ], Context::createDefaultContext());

        $this->connection->beginTransaction();
        static::assertSame($firstOrganization, $this->connection->fetchOne(
            'SELECT LOWER(HEX(unit.organization_id)) FROM user_organization_unit AS membership
             INNER JOIN organization_unit AS unit ON unit.id = membership.organization_unit_id WHERE membership.id = UNHEX(?)',
            [$employeeMembership],
        ));
        $this->otherConnection->update('user_organization_unit', ['organization_unit_id' => Uuid::fromHexToBytes($secondUnit)], ['id' => Uuid::fromHexToBytes($employeeMembership)]);

        $this->expectException(WriteException::class);
        $repository->update([['id' => $employeeMembership, 'supervisorMembershipId' => $managerMembership]], Context::createDefaultContext());
    }

    public function testRetriedPositionInsertionResolvesTheCurrentPrimaryDepartment(): void
    {
        $userId = $this->createUser();
        [, $secondUnit] = $this->createOrganization();
        static::getContainer()->get('user_organization_unit.repository')->create([[
            'userId' => $userId, 'organizationUnitId' => $secondUnit,
        ]], Context::createDefaultContext());
        $positionId = $this->createPosition();
        $assignmentId = Uuid::randomHex();
        $this->forceRetryAfterPreparation(function () use ($userId, $secondUnit): void {
            $this->otherConnection->executeStatement(
                'UPDATE user_organization_unit SET is_primary = (organization_unit_id = UNHEX(?)) WHERE user_id = UNHEX(?)',
                [$secondUnit, $userId],
            );
        });

        static::getContainer()->get('user_position.repository')->create([[
            'id' => $assignmentId, 'userId' => $userId, 'positionId' => $positionId,
        ]], Context::createDefaultContext());

        static::assertSame($secondUnit, $this->connection->fetchOne('SELECT LOWER(HEX(organization_unit_id)) FROM user_position WHERE id = UNHEX(?)', [$assignmentId]));
    }

    public function testRetriedAutomaticPrimaryDoesNotOverrideAnotherCommittedPrimary(): void
    {
        $userId = $this->createUser();
        $this->connection->update('user', ['admin' => 1], ['id' => Uuid::fromHexToBytes($userId)]);
        $this->connection->delete('user_position', ['user_id' => Uuid::fromHexToBytes($userId)]);
        $positionId = $this->createPosition();
        $newAssignmentId = Uuid::randomHex();
        $committedAssignmentId = Uuid::randomHex();
        $this->forceRetryAfterPreparation(function () use ($userId, $committedAssignmentId): void {
            $this->otherConnection->insert('user_position', [
                'id' => Uuid::fromHexToBytes($committedAssignmentId), 'user_id' => Uuid::fromHexToBytes($userId),
                'position_id' => Uuid::fromHexToBytes(OrganizationDefaults::POSITION),
                'organization_unit_id' => Uuid::fromHexToBytes(OrganizationDefaults::UNIT), 'is_primary' => 1,
                'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        });

        static::getContainer()->get('user_position.repository')->create([[
            'id' => $newAssignmentId, 'userId' => $userId, 'positionId' => $positionId,
        ]], Context::createDefaultContext());

        static::assertSame([$committedAssignmentId], $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM user_position WHERE user_id = UNHEX(?) AND is_primary = 1', [$userId]));
    }

    private function forceRetryAfterPreparation(\Closure $concurrentWrite): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on(PreWriteValidationEvent::class, function () use ($concurrentWrite): void {
            // Release the failed attempt's locks before simulating the concurrent commit.
            $this->connection->rollBack();
            $concurrentWrite();
            $this->connection->beginTransaction();
            throw new OrganizationAssignmentRetryException('Retry the prepared assignment.');
        }, once: true);
    }

    private function createUser(): string
    {
        $id = Uuid::randomHex();
        static::getContainer()->get('user.repository')->create([[
            'id' => $id, 'username' => $id, 'name' => 'Organization concurrency test',
            'email' => $id . '@example.com', 'password' => 'shopwell', 'active' => true,
            'localeId' => Uuid::fromBytesToHex($this->connection->fetchOne('SELECT id FROM locale LIMIT 1')),
        ]], Context::createDefaultContext());
        $this->userIds[] = $id;

        return $id;
    }

    /**
     * @return array{string, string}
     */
    private function createOrganization(): array
    {
        $organizationId = Uuid::randomHex();
        $unitId = Uuid::randomHex();
        static::getContainer()->get('organization.repository')->create([[
            'id' => $organizationId, 'name' => 'Concurrency test organization', 'active' => true,
            'units' => [['id' => $unitId, 'name' => 'Concurrency department', 'active' => true]],
        ]], Context::createDefaultContext());
        $this->organizationIds[] = $organizationId;

        return [$organizationId, $unitId];
    }

    private function createPosition(): string
    {
        $id = Uuid::randomHex();
        static::getContainer()->get('position.repository')->create([['id' => $id, 'name' => 'Concurrency test position', 'active' => true]], Context::createDefaultContext());
        $this->positionIds[] = $id;

        return $id;
    }
}

/**
 * @internal
 */
class OrganizationAssignmentRetryException extends \RuntimeException implements Exception, RetryableException
{
}

<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\User\Aggregate\UserOrganizationUnit;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\User\Aggregate\UserOrganizationUnit\UserOrganizationUnitPrimarySubscriber;

/**
 * "Primary organization unit" is a single valued fact stored in a multi valued table, so the
 * subscriber is what keeps the second primary from existing. The strict `isPrimary === true` check is
 * the interesting part: a payload carrying the integer 1 or the string "1" is a write from somewhere
 * else and must not demote the mapping a user actually chose.
 *
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(UserOrganizationUnitPrimarySubscriber::class)]
class UserOrganizationUnitPrimarySubscriberTest extends TestCase
{
    public function testTheSubscriberListensToTheMappingWrite(): void
    {
        static::assertSame(
            ['user_organization_unit.written' => 'enforceSinglePrimary'],
            UserOrganizationUnitPrimarySubscriber::getSubscribedEvents()
        );
    }

    public function testANewPrimaryDemotesEveryOtherMappingOfTheUser(): void
    {
        $userId = Uuid::randomHex();
        $mappingId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with(
                'SELECT `user_id` FROM `user_organization_unit` WHERE `id` = :id',
                ['id' => Uuid::fromHexToBytes($mappingId)]
            )
            ->willReturn(Uuid::fromHexToBytes($userId));
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                static::logicalAnd(
                    static::stringContains('SET `is_primary` = 0'),
                    static::stringContains('WHERE `user_id` = :userId'),
                    static::stringContains('AND `id` != :id')
                ),
                [
                    'userId' => Uuid::fromHexToBytes($userId),
                    'id' => Uuid::fromHexToBytes($mappingId),
                ]
            );

        $subscriber = new UserOrganizationUnitPrimarySubscriber($connection);
        $subscriber->enforceSinglePrimary($this->event([
            ['id' => $mappingId, 'isPrimary' => true],
        ]));
    }

    public function testANonPrimaryMappingDoesNotTouchTheOtherRows(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchOne');
        $connection->expects($this->never())->method('executeStatement');

        $subscriber = new UserOrganizationUnitPrimarySubscriber($connection);
        $subscriber->enforceSinglePrimary($this->event([
            ['id' => Uuid::randomHex(), 'isPrimary' => false],
        ]));
    }

    public function testATruthyButNotStrictlyBooleanFlagIsNotTreatedAsPrimary(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchOne');
        $connection->expects($this->never())->method('executeStatement');

        $subscriber = new UserOrganizationUnitPrimarySubscriber($connection);
        $subscriber->enforceSinglePrimary($this->event([
            ['id' => Uuid::randomHex(), 'isPrimary' => 1],
        ]));
    }

    public function testAMappingWithoutAnIdIsSkippedInsteadOfDemotingTheWrongUser(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchOne');
        $connection->expects($this->never())->method('executeStatement');

        $subscriber = new UserOrganizationUnitPrimarySubscriber($connection);
        $subscriber->enforceSinglePrimary($this->event([
            ['userId' => Uuid::randomHex(), 'isPrimary' => true],
            ['organizationUnitId' => Uuid::randomHex(), 'isPrimary' => true],
        ]));
    }

    public function testAMappingWithoutAStoredUserDoesNotTouchTheOtherRows(): void
    {
        $mappingId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with(
                'SELECT `user_id` FROM `user_organization_unit` WHERE `id` = :id',
                ['id' => Uuid::fromHexToBytes($mappingId)]
            )
            ->willReturn(false);
        $connection->expects($this->never())->method('executeStatement');

        $subscriber = new UserOrganizationUnitPrimarySubscriber($connection);
        $subscriber->enforceSinglePrimary($this->event([
            ['id' => $mappingId, 'isPrimary' => true],
        ]));
    }

    /**
     * @param list<array<string, mixed>> $payloads
     */
    private function event(array $payloads): EntityWrittenEvent
    {
        $results = [];

        foreach ($payloads as $index => $payload) {
            $results[] = new EntityWriteResult(
                'result-' . $index,
                $payload,
                'user_organization_unit',
                EntityWriteResult::OPERATION_UPDATE,
            );
        }

        return new EntityWrittenEvent(
            'user_organization_unit',
            $results,
            Context::createDefaultContext(),
        );
    }
}

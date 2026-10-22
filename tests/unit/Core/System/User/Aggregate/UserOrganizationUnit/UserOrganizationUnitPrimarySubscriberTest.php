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
        $unitId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                static::stringContains('SET `is_primary` = 0'),
                [
                    'userId' => Uuid::fromHexToBytes($userId),
                    'organizationUnitId' => Uuid::fromHexToBytes($unitId),
                ]
            );

        $subscriber = new UserOrganizationUnitPrimarySubscriber($connection);
        $subscriber->enforceSinglePrimary($this->event([
            ['userId' => $userId, 'organizationUnitId' => $unitId, 'isPrimary' => true],
        ]));
    }

    public function testANonPrimaryMappingDoesNotTouchTheOtherRows(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $subscriber = new UserOrganizationUnitPrimarySubscriber($connection);
        $subscriber->enforceSinglePrimary($this->event([
            ['userId' => Uuid::randomHex(), 'organizationUnitId' => Uuid::randomHex(), 'isPrimary' => false],
        ]));
    }

    public function testATruthyButNotStrictlyBooleanFlagIsNotTreatedAsPrimary(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $subscriber = new UserOrganizationUnitPrimarySubscriber($connection);
        $subscriber->enforceSinglePrimary($this->event([
            ['userId' => Uuid::randomHex(), 'organizationUnitId' => Uuid::randomHex(), 'isPrimary' => 1],
        ]));
    }

    public function testAMappingWithoutBothKeysIsSkippedInsteadOfDemotingTheWrongUser(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $subscriber = new UserOrganizationUnitPrimarySubscriber($connection);
        $subscriber->enforceSinglePrimary($this->event([
            ['userId' => Uuid::randomHex(), 'isPrimary' => true],
            ['organizationUnitId' => Uuid::randomHex(), 'isPrimary' => true],
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

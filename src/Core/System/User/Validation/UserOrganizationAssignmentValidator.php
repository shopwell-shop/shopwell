<?php declare(strict_types=1);

namespace Shopwell\Core\System\User\Validation;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\WriteConstraintViolationException;
use Shopwell\Core\System\User\Aggregate\UserOrganizationUnit\UserOrganizationUnitDefinition;
use Shopwell\Core\System\User\Aggregate\UserPosition\UserPositionDefinition;
use Shopwell\Core\System\User\UserDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Maintains primary assignments inside the DAL write transaction, including nested writes and deletes.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\System\User\UserOrganizationAssignmentTest
 */
#[Package('fundamentals@framework')]
class UserOrganizationAssignmentValidator implements EventSubscriberInterface
{
    /**
     * @var \WeakMap<WriteContext, list<string>>
     */
    private \WeakMap $affectedUsers;

    /**
     * @var \WeakMap<InsertCommand, int>
     */
    private \WeakMap $automaticPrimaries;

    /**
     * @var \WeakMap<InsertCommand, true>
     */
    private \WeakMap $inferredDepartments;

    public function __construct(private readonly Connection $connection)
    {
        $this->affectedUsers = new \WeakMap();
        $this->automaticPrimaries = new \WeakMap();
        $this->inferredDepartments = new \WeakMap();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => ['prepareAssignments', 100],
            PostWriteValidationEvent::class => 'validateAssignments',
        ];
    }

    public function prepareAssignments(PreWriteValidationEvent $event): void
    {
        $userIds = $this->findAffectedUsers($event);
        if ($userIds === []) {
            return;
        }

        // Serialize primary changes for a user, including assignments in different organizations.
        $this->connection->fetchFirstColumn(
            'SELECT id FROM `user` WHERE id IN (:ids) ORDER BY id FOR UPDATE',
            ['ids' => $userIds],
            ['ids' => ArrayParameterType::BINARY],
        );
        $this->affectedUsers[$event->getWriteContext()] = $userIds;

        $this->preparePrimaryAssignments($event, UserOrganizationUnitDefinition::ENTITY_NAME);
        $this->preparePrimaryAssignments($event, UserPositionDefinition::ENTITY_NAME);
        $this->assignPositionDepartments($event);
    }

    public function validateAssignments(PostWriteValidationEvent $event): void
    {
        $userIds = $this->affectedUsers[$event->getWriteContext()] ?? [];
        unset($this->affectedUsers[$event->getWriteContext()]);
        if ($userIds === []) {
            return;
        }

        foreach ([UserOrganizationUnitDefinition::ENTITY_NAME, UserPositionDefinition::ENTITY_NAME] as $entity) {
            $assignments = $this->connection->fetchAllAssociative(
                'SELECT `user`.id, assignment.is_primary FROM `user`
                 LEFT JOIN `' . $entity . '` AS assignment ON assignment.user_id = `user`.id
                 WHERE `user`.id IN (:ids) AND `user`.admin = 0 FOR UPDATE',
                ['ids' => $userIds],
                ['ids' => ArrayParameterType::BINARY],
            );
            $primaryCounts = [];
            foreach ($assignments as $assignment) {
                $userId = $assignment['id'];
                $primaryCounts[$userId] = ($primaryCounts[$userId] ?? 0) + (int) $assignment['is_primary'];
            }
            $invalidUsers = array_filter($primaryCounts, static fn (int $count) => $count !== 1);
            if ($invalidUsers !== []) {
                $this->addViolation($event, 'A user must have exactly one primary department and one primary position.', 'USER__PRIMARY_ASSIGNMENT_REQUIRED', '/' . $entity);
            }
        }

        $this->refreshAssignmentIds($userIds);
    }

    /**
     * @return list<string>
     */
    private function findAffectedUsers(PreWriteValidationEvent $event): array
    {
        $userIds = [];
        foreach ($event->getCommandsForEntity(UserDefinition::ENTITY_NAME) as $command) {
            if ($command instanceof InsertCommand || $command->hasField('admin')) {
                $userIds[] = $command->getPrimaryKey()['id'];
            }
        }

        foreach ([UserOrganizationUnitDefinition::ENTITY_NAME, UserPositionDefinition::ENTITY_NAME] as $entity) {
            foreach ($event->getCommandsForEntity($entity) as $command) {
                $userId = $this->findUserId($command);
                if ($userId !== null) {
                    $userIds[] = $userId;
                }
                $existingUserId = $this->connection->fetchOne('SELECT user_id FROM `' . $entity . '` WHERE id = ? FOR UPDATE', [$command->getPrimaryKey()['id']]);
                if (\is_string($existingUserId)) {
                    $userIds[] = $existingUserId;
                }
            }
        }

        return array_values(array_unique($userIds));
    }

    private function findUserId(WriteCommand $command): ?string
    {
        $userId = $command->getPayload()['user_id'] ?? null;
        if (\is_string($userId)) {
            return $userId;
        }

        $userId = $this->connection->fetchOne(
            'SELECT user_id FROM `' . $command->getEntityName() . '` WHERE id = :id FOR UPDATE',
            ['id' => $command->getPrimaryKey()['id']],
        );

        return \is_string($userId) ? $userId : null;
    }

    private function preparePrimaryAssignments(PreWriteValidationEvent $event, string $entity): void
    {
        $requestedPrimary = [];
        $newAssignments = [];
        foreach ($event->getCommandsForEntity($entity) as $command) {
            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            $userId = $this->findUserId($command);
            if ($userId === null) {
                continue;
            }

            if ($command instanceof InsertCommand) {
                // The gateway reuses commands after a rollback; only caller-requested primaries stay authoritative.
                if (isset($this->automaticPrimaries[$command])) {
                    $command->addPayload('is_primary', $this->automaticPrimaries[$command]);
                }
                $newAssignments[$userId][] = $command;
            }
            if (!(bool) ($command->getPayload()['is_primary'] ?? false)) {
                continue;
            }
            if (isset($requestedPrimary[$userId])) {
                $this->addViolation($event, 'Only one primary assignment may be requested per user.', 'USER__MULTIPLE_PRIMARY_ASSIGNMENTS', $command->getPath());
                continue;
            }

            $requestedPrimary[$userId] = $command;
        }

        foreach ($newAssignments as $userId => $commands) {
            if (isset($requestedPrimary[$userId])) {
                continue;
            }

            $existingPrimary = $this->connection->fetchOne('SELECT id FROM `' . $entity . '` WHERE user_id = ? AND is_primary = 1 LIMIT 1 FOR UPDATE', [$userId]);
            if ($existingPrimary === false) {
                $this->automaticPrimaries[$commands[0]] = (int) ($commands[0]->getPayload()['is_primary'] ?? 0);
                $commands[0]->addPayload('is_primary', 1);
                $requestedPrimary[$userId] = $commands[0];
            }
        }

        foreach ($requestedPrimary as $userId => $command) {
            $this->connection->executeStatement(
                'UPDATE `' . $entity . '` SET is_primary = 0 WHERE user_id = :userId AND id <> :id AND is_primary = 1',
                ['userId' => $userId, 'id' => $command->getPrimaryKey()['id']],
            );
        }
    }

    private function assignPositionDepartments(PreWriteValidationEvent $event): void
    {
        $primaryDepartments = [];
        foreach ($event->getCommandsForEntity(UserOrganizationUnitDefinition::ENTITY_NAME) as $command) {
            $payload = $command->getPayload();
            if (!(bool) ($payload['is_primary'] ?? false)) {
                continue;
            }

            $userId = $this->findUserId($command);
            $unitId = $payload['organization_unit_id'] ?? $this->connection->fetchOne(
                'SELECT organization_unit_id FROM user_organization_unit WHERE id = ? FOR UPDATE',
                [$command->getPrimaryKey()['id']],
            );
            if ($userId !== null && \is_string($unitId)) {
                $primaryDepartments[$userId] = $unitId;
            }
        }

        foreach ($event->getCommandsForEntity(UserPositionDefinition::ENTITY_NAME) as $command) {
            if (!$command instanceof InsertCommand) {
                continue;
            }

            if ($command->hasField('organization_unit_id') && !isset($this->inferredDepartments[$command])) {
                continue;
            }

            $userId = $this->findUserId($command);
            if ($userId === null) {
                continue;
            }

            $unitId = $primaryDepartments[$userId] ?? $this->connection->fetchOne(
                'SELECT organization_unit_id FROM user_organization_unit WHERE user_id = ? AND is_primary = 1 LIMIT 1 FOR UPDATE',
                [$userId],
            );
            if (!\is_string($unitId)) {
                $this->addViolation($event, 'A position assignment requires a department membership.', 'USER__POSITION_DEPARTMENT_REQUIRED', $command->getPath());
                continue;
            }

            $this->inferredDepartments[$command] = true;
            $command->addPayload('organization_unit_id', $unitId);
        }
    }

    /**
     * @param list<string> $userIds
     */
    private function refreshAssignmentIds(array $userIds): void
    {
        $assignments = [
            'user_organization_unit' => ['organization_unit_id', 'organization_unit_ids'],
            'user_position' => ['position_id', 'position_ids'],
        ];
        foreach ($assignments as $table => [$referenceColumn, $idsColumn]) {
            $this->connection->executeStatement(
                'UPDATE `user`
                 LEFT JOIN (
                     SELECT user_id, JSON_ARRAYAGG(assignment_id) AS ids FROM (
                         SELECT DISTINCT user_id, LOWER(HEX(`' . $referenceColumn . '`)) AS assignment_id
                         FROM `' . $table . '` WHERE user_id IN (:ids)
                     ) AS unique_assignments GROUP BY user_id
                 ) AS assignments ON assignments.user_id = `user`.id
                 SET `user`.`' . $idsColumn . '` = COALESCE(assignments.ids, JSON_ARRAY())
                 WHERE `user`.id IN (:ids)',
                ['ids' => $userIds],
                ['ids' => ArrayParameterType::BINARY],
            );
        }
    }

    private function addViolation(PreWriteValidationEvent $event, string $message, string $code, string $path): void
    {
        $event->getExceptions()->add(new WriteConstraintViolationException(new ConstraintViolationList([
            new ConstraintViolation($message, $message, [], null, $path, null, code: $code),
        ])));
    }
}

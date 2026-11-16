<?php declare(strict_types=1);

namespace Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\WriteConstraintViolationException;
use Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\OrganizationUnitDefinition;
use Shopwell\Core\System\User\Aggregate\UserOrganizationUnit\UserOrganizationUnitDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Validates the final reporting graph so batch and nested writes obey the same rules.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\System\User\UserOrganizationAssignmentTest
 */
#[Package('fundamentals@discovery')]
class OrganizationReportingValidator implements EventSubscriberInterface
{
    /**
     * @var \WeakMap<WriteContext, list<string>>
     */
    private \WeakMap $affectedOrganizations;

    public function __construct(private readonly Connection $connection)
    {
        $this->affectedOrganizations = new \WeakMap();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'lockOrganizations',
            PostWriteValidationEvent::class => 'validateReportingLines',
        ];
    }

    public function lockOrganizations(PreWriteValidationEvent $event): void
    {
        $unitIds = [];
        $organizationIds = [];
        foreach ($event->getCommandsForEntity(OrganizationUnitDefinition::ENTITY_NAME) as $command) {
            $unitIds[] = $command->getPrimaryKey()['id'];
            $organizationId = $command->getPayload()['organization_id'] ?? null;
            if (\is_string($organizationId)) {
                $organizationIds[] = $organizationId;
            }
        }
        foreach ($event->getCommandsForEntity(UserOrganizationUnitDefinition::ENTITY_NAME) as $command) {
            $unitId = $command->getPayload()['organization_unit_id'] ?? null;
            if (\is_string($unitId)) {
                $unitIds[] = $unitId;
            }
            $existingUnitId = $this->connection->fetchOne('SELECT organization_unit_id FROM user_organization_unit WHERE id = ? FOR UPDATE', [$command->getPrimaryKey()['id']]);
            if (\is_string($existingUnitId)) {
                $unitIds[] = $existingUnitId;
            }
        }
        if ($unitIds !== []) {
            $organizationIds = array_merge($organizationIds, $this->connection->fetchFirstColumn(
                'SELECT organization_id FROM organization_unit WHERE id IN (:ids) ORDER BY id FOR UPDATE',
                ['ids' => array_values(array_unique($unitIds))],
                ['ids' => ArrayParameterType::BINARY],
            ));
        }
        $organizationIds = array_values(array_unique(array_filter($organizationIds, \is_string(...))));
        if ($organizationIds === []) {
            return;
        }

        // Reporting edges can connect different users; a user lock alone cannot prevent concurrent cycles.
        $this->connection->fetchFirstColumn(
            'SELECT id FROM organization WHERE id IN (:ids) ORDER BY id FOR UPDATE',
            ['ids' => $organizationIds],
            ['ids' => ArrayParameterType::BINARY],
        );
        $this->affectedOrganizations[$event->getWriteContext()] = $organizationIds;
    }

    public function validateReportingLines(PostWriteValidationEvent $event): void
    {
        $organizationIds = $this->affectedOrganizations[$event->getWriteContext()] ?? [];
        unset($this->affectedOrganizations[$event->getWriteContext()]);
        if ($organizationIds === []) {
            return;
        }

        $memberships = $this->connection->fetchAllAssociative(
            'SELECT membership.id, membership.user_id, membership.supervisor_membership_id, membership.leader_position,
                    unit.organization_id, supervisor_unit.organization_id AS supervisor_organization_id
             FROM user_organization_unit AS membership
             INNER JOIN organization_unit AS unit ON unit.id = membership.organization_unit_id
             LEFT JOIN user_organization_unit AS supervisor ON supervisor.id = membership.supervisor_membership_id
             LEFT JOIN organization_unit AS supervisor_unit ON supervisor_unit.id = supervisor.organization_unit_id
             WHERE unit.organization_id IN (:ids) FOR UPDATE',
            ['ids' => $organizationIds],
            ['ids' => ArrayParameterType::BINARY],
        );
        $membershipsById = array_column($memberships, null, 'id');
        $violations = new ConstraintViolationList();
        foreach ($memberships as $membership) {
            $path = '/organizationUnitMappings/' . bin2hex($membership['id']);
            if ((int) $membership['leader_position'] < 0) {
                $this->addViolation($violations, 'A department leader order must not be negative.', 'USER__INVALID_LEADER_POSITION', $path . '/leaderPosition');
            }
            if ($membership['supervisor_membership_id'] === null) {
                continue;
            }
            if ($membership['organization_id'] !== $membership['supervisor_organization_id']) {
                $this->addViolation($violations, 'A direct supervisor must belong to the same organization.', 'USER__SUPERVISOR_ORGANIZATION_MISMATCH', $path . '/supervisorMembershipId');
                continue;
            }
            if ($this->hasSupervisorCycle($membership['id'], $membershipsById)) {
                $this->addViolation($violations, 'A direct supervisor relationship must not contain a cycle or supervise the same user.', 'USER__SUPERVISOR_CYCLE', $path . '/supervisorMembershipId');
            }
        }

        $invalidParents = $this->connection->fetchFirstColumn(
            'SELECT unit.id FROM organization_unit AS unit
             INNER JOIN organization_unit AS parent ON parent.id = unit.parent_id
             WHERE unit.organization_id IN (:ids) AND unit.organization_id <> parent.organization_id FOR UPDATE',
            ['ids' => $organizationIds],
            ['ids' => ArrayParameterType::BINARY],
        );
        foreach ($invalidParents as $id) {
            $this->addViolation($violations, 'A parent department must belong to the same organization.', 'ORGANIZATION__PARENT_MISMATCH', '/organizationUnits/' . bin2hex($id) . '/parentId');
        }

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }

    /**
     * @param array<string, array<string, mixed>> $memberships
     */
    private function hasSupervisorCycle(string $membershipId, array $memberships): bool
    {
        $visitedUsers = [];
        while (isset($memberships[$membershipId])) {
            $membership = $memberships[$membershipId];
            $userId = $membership['user_id'];
            if (isset($visitedUsers[$userId])) {
                return true;
            }

            $visitedUsers[$userId] = true;
            if ($membership['supervisor_membership_id'] === null) {
                return false;
            }

            $membershipId = $membership['supervisor_membership_id'];
        }

        return false;
    }

    private function addViolation(ConstraintViolationList $violations, string $message, string $code, string $path): void
    {
        $violations->add(new ConstraintViolation($message, $message, [], null, $path, null, code: $code));
    }
}

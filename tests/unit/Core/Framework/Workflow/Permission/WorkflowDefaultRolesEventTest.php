<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Permission;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Permission\WorkflowDefaultRolesEvent;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowDefaultRolesEvent::class)]
class WorkflowDefaultRolesEventTest extends TestCase
{
    public function testDefaultPrivilegesSeparateDesignManagementFromWorkflowParticipation(): void
    {
        $event = new WorkflowDefaultRolesEvent();

        $participant = $event->getPrivileges(WorkflowDefaultRolesEvent::PARTICIPANT);
        foreach (['workflow:read', 'workflow_participant.viewer', 'workflow_participant.creator', 'workflow_participant.approver', 'workflow_instance:access', 'workflow_instance:start', 'workflow_task:decide', 'user:read', 'state_machine_state:read', 'organization:read', 'organization_unit:read'] as $privilege) {
            static::assertContains($privilege, $participant);
        }
        foreach (['workflow:update', 'workflow:publish', 'workflow_draft:read'] as $privilege) {
            static::assertNotContains($privilege, $participant);
        }
        static::assertSame([
            'workflow.viewer', 'workflow.creator', 'workflow.editor', 'workflow.deleter', 'workflow.publisher',
            'workflow:read', 'workflow:create', 'workflow:update', 'workflow:delete',
            'workflow:publish', 'workflow_draft:read', 'position:read', 'acl_role:read', 'user:read', 'organization:read', 'organization_unit:read',
        ], $event->getPrivileges(WorkflowDefaultRolesEvent::MANAGER));
    }

    public function testExtensionPrivilegesAreDeduplicatedWithoutChangingOtherRoles(): void
    {
        $event = new WorkflowDefaultRolesEvent();
        $managerPrivileges = $event->getPrivileges(WorkflowDefaultRolesEvent::MANAGER);
        $participantPrivileges = $event->getPrivileges(WorkflowDefaultRolesEvent::PARTICIPANT);

        $event->addPrivileges(WorkflowDefaultRolesEvent::PARTICIPANT, ['workflow:read', 'example:participate', 'example:participate']);

        static::assertSame([...$participantPrivileges, 'example:participate'], $event->getPrivileges(WorkflowDefaultRolesEvent::PARTICIPANT));
        static::assertSame($managerPrivileges, $event->getPrivileges(WorkflowDefaultRolesEvent::MANAGER));
    }

    public function testUnknownRoleCannotBeAdded(): void
    {
        $event = new WorkflowDefaultRolesEvent();

        $this->expectExceptionObject(WorkflowException::unknownDefaultRole('unknown'));

        $event->addPrivileges('unknown', ['workflow:read']);
    }

    public function testMenuSelectionsOnlyIncludeRoleSpecificLeavesAndAllowExtensions(): void
    {
        $event = new WorkflowDefaultRolesEvent();

        $event->addMenuKeys(WorkflowDefaultRolesEvent::PARTICIPANT, ['example-inbox', 'sw-workflow-tasks']);

        static::assertSame(['sw-workflow-design'], $event->getMenuKeys(WorkflowDefaultRolesEvent::MANAGER));
        static::assertSame(['sw-workflow-tasks', 'example-inbox'], $event->getMenuKeys(WorkflowDefaultRolesEvent::PARTICIPANT));
    }

    public function testMenuSelectionRejectsAnUnknownRole(): void
    {
        $event = new WorkflowDefaultRolesEvent();

        $this->expectExceptionObject(WorkflowException::unknownDefaultRole('unknown'));

        $event->addMenuKeys('unknown', ['example-inbox']);
    }
}

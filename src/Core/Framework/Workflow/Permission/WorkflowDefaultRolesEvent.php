<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Permission;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Extensions can add privileges before missing default workflow roles are installed.
 * Existing roles are never changed by this event.
 */
#[Package('framework')]
final class WorkflowDefaultRolesEvent extends Event
{
    public const MANAGER = 'manager';
    public const PARTICIPANT = 'participant';

    /**
     * @var array<string, list<string>>
     */
    private array $privileges = [
        self::MANAGER => [
            'workflow.viewer',
            'workflow.creator',
            'workflow.editor',
            'workflow.deleter',
            'workflow.publisher',
            'workflow:read',
            'workflow:create',
            'workflow:update',
            'workflow:delete',
            'workflow:publish',
            'workflow_draft:read',
            'position:read',
            'acl_role:read',
            'user:read',
            'organization:read',
            'organization_unit:read',
        ],
        self::PARTICIPANT => [
            'workflow:read',
            'workflow_participant.viewer',
            'workflow_participant.creator',
            'workflow_participant.approver',
            'workflow_instance:access',
            'workflow_instance:start',
            'workflow_task:decide',
            'user:read',
            'organization:read',
            'organization_unit:read',
            'state_machine_state:read',
        ],
    ];

    /**
     * @var array<string, list<string>>
     */
    private array $menuKeys = [
        self::MANAGER => ['sw-workflow-design'],
        self::PARTICIPANT => ['sw-workflow-tasks'],
    ];

    /**
     * @param list<string> $menuKeys
     */
    public function addMenuKeys(string $role, array $menuKeys): void
    {
        $this->menuKeys[$role] = array_values(array_unique([...$this->getMenuKeys($role), ...$menuKeys]));
    }

    /**
     * @return list<string>
     */
    public function getMenuKeys(string $role): array
    {
        if (!isset($this->menuKeys[$role])) {
            throw WorkflowException::unknownDefaultRole($role);
        }

        return $this->menuKeys[$role];
    }

    /**
     * @param list<string> $privileges
     */
    public function addPrivileges(string $role, array $privileges): void
    {
        $this->privileges[$role] = array_values(array_unique([...$this->getPrivileges($role), ...$privileges]));
    }

    /**
     * @return list<string>
     */
    public function getPrivileges(string $role): array
    {
        if (!isset($this->privileges[$role])) {
            throw WorkflowException::unknownDefaultRole($role);
        }

        return $this->privileges[$role];
    }
}

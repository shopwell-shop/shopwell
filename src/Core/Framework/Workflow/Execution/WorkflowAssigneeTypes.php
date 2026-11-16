<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Shopwell\Core\Framework\Log\Package;

/**
 * Assignee types resolved by the registry; organization-backed sources use the initiator's primary membership.
 *
 * @internal
 */
#[Package('framework')]
final class WorkflowAssigneeTypes
{
    /**
     * Explicit user IDs in assigneeValue.
     */
    public const USER = 'user';

    /**
     * The initiator; assigneeValue remains empty.
     */
    public const INITIATOR = 'initiator';

    /**
     * Leader resolution requires an extension.
     */
    public const LEADER = 'leader';

    /**
     * Platform ACL role IDs in assigneeValue.
     */
    public const ROLE = 'role';

    /**
     * The ordered leaders of the initiator's primary department.
     */
    public const UNIT_LEADER = 'unitLeader';

    public const ALL = [self::USER, self::INITIATOR, self::LEADER, self::ROLE, self::UNIT_LEADER];

    /**
     * Types requiring assigneeValue; other types must leave it empty.
     */
    public const NEEDS_VALUE = [self::USER, self::ROLE];

    /**
     * Reserved sources: FORM_FIELD, INITIATOR_LEADER_LEVEL2 and DEPT_LEADER_OF_USER.
     */
    public const FUTURE_FORM_FIELD = 'formField';
    public const FUTURE_LEADER_LEVEL2 = 'leaderLevel2';
}

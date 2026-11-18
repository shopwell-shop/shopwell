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
     * Follow the initiator’s captured department reporting relationship.
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

    /**
     * Positions held within the initiator's primary department.
     */
    public const POSITION = 'position';

    /**
     * A validated people field in the published request schema.
     */
    public const FORM_FIELD = 'formField';

    public const ALL = [self::USER, self::INITIATOR, self::LEADER, self::ROLE, self::UNIT_LEADER, self::POSITION, self::FORM_FIELD];

    /**
     * Types requiring assigneeValue; other types must leave it empty.
     */
    public const NEEDS_VALUE = [self::USER, self::ROLE, self::POSITION, self::FORM_FIELD];

    /**
     * Reserved source for a future reporting-line variation.
     */
    public const FUTURE_LEADER_LEVEL2 = 'leaderLevel2';
}

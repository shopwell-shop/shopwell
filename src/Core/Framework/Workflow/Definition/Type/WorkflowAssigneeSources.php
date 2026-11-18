<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Definition\Type;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class WorkflowAssigneeSources
{
    public const FORM_FIELD = 'core:form-field';
    public const USER = 'core:user';
    public const USERS = 'core:users';
    public const DEPARTMENT_LEADERS = 'core:department-leaders';
    public const POSITIONS = 'core:positions';
    public const INITIATOR = 'core:initiator';
    public const ROLES = 'core:roles';
    public const SUPERVISORS = 'core:supervisors';
}

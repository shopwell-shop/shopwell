<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Definition\Type;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class WorkflowAssigneeSources
{
    public const USER = 'core:user';
    public const USERS = 'core:users';
    public const DEPARTMENT_LEADERS = 'core:department-leaders';
}

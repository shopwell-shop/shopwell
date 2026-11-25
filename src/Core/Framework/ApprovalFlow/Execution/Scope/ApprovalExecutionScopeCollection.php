<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Scope;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ApprovalExecutionScopeEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalExecutionScopeCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'approval_flow_execution_scope_collection';
    }

    protected function getExpectedClass(): string
    {
        return ApprovalExecutionScopeEntity::class;
    }
}

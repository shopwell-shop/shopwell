<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Branch;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ApprovalExecutionBranchEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalExecutionBranchCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'approval_flow_execution_branch_collection';
    }

    protected function getExpectedClass(): string
    {
        return ApprovalExecutionBranchEntity::class;
    }
}

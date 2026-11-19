<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Node;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ApprovalNodeExecutionEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalNodeExecutionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'approval_flow_execution_collection';
    }

    protected function getExpectedClass(): string
    {
        return ApprovalNodeExecutionEntity::class;
    }
}

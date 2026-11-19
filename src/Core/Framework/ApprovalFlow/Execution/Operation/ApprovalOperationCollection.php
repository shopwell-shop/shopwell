<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Operation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ApprovalOperationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalOperationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'approval_flow_operation_collection';
    }

    protected function getExpectedClass(): string
    {
        return ApprovalOperationEntity::class;
    }
}

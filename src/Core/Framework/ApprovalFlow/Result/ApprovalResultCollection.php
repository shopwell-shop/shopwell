<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Result;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ApprovalResultEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalResultCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'approval_flow_result_collection';
    }

    protected function getExpectedClass(): string
    {
        return ApprovalResultEntity::class;
    }
}

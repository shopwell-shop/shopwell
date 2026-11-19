<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Definition;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ApprovalFlowEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalFlowCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'approval_flow_definition_collection';
    }

    protected function getExpectedClass(): string
    {
        return ApprovalFlowEntity::class;
    }
}

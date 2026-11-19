<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Instance;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ApprovalInstanceEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalInstanceCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'approval_flow_instance_collection';
    }

    protected function getExpectedClass(): string
    {
        return ApprovalInstanceEntity::class;
    }
}

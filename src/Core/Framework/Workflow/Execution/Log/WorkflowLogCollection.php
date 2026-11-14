<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Log;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<WorkflowLogEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class WorkflowLogCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'workflow_log_collection';
    }

    protected function getExpectedClass(): string
    {
        return WorkflowLogEntity::class;
    }
}

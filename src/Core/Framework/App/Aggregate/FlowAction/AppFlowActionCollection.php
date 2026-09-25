<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Aggregate\FlowAction;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<AppFlowActionEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class AppFlowActionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'app_flow_action_collection';
    }

    protected function getExpectedClass(): string
    {
        return AppFlowActionEntity::class;
    }
}

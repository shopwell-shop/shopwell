<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Aggregate\FlowEvent;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<AppFlowEventEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class AppFlowEventCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'app_flow_event_collection';
    }

    protected function getExpectedClass(): string
    {
        return AppFlowEventEntity::class;
    }
}

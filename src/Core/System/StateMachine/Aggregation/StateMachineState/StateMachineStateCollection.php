<?php declare(strict_types=1);

namespace Shopwell\Core\System\StateMachine\Aggregation\StateMachineState;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<StateMachineStateEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class StateMachineStateCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'state_machine_state_collection';
    }

    protected function getExpectedClass(): string
    {
        return StateMachineStateEntity::class;
    }
}

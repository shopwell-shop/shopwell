<?php declare(strict_types=1);

namespace Shopwell\Core\System\StateMachine\Aggregation\StateMachineTransition;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<StateMachineTransitionEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class StateMachineTransitionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'state_machine_transition_collection';
    }

    protected function getExpectedClass(): string
    {
        return StateMachineTransitionEntity::class;
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\System\StateMachine;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<StateMachineEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class StateMachineCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'state_machine_collection';
    }

    protected function getExpectedClass(): string
    {
        return StateMachineEntity::class;
    }
}

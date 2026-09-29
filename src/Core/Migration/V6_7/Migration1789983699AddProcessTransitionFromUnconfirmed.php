<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Migration\Traits\StateMachineMigration;
use Shopwell\Core\Migration\Traits\StateMachineMigrationTrait;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1789983699AddProcessTransitionFromUnconfirmed extends MigrationStep
{
    use StateMachineMigrationTrait;

    public function getCreationTimestamp(): int
    {
        return 1789983699;
    }

    public function update(Connection $connection): void
    {
        $this->import(
            new StateMachineMigration(
                OrderTransactionStates::STATE_MACHINE,
                '支付状态',
                'Payment state',
                [],
                [
                    StateMachineMigration::transition(
                        StateMachineTransitionActions::ACTION_PROCESS,
                        OrderTransactionStates::STATE_UNCONFIRMED,
                        OrderTransactionStates::STATE_IN_PROGRESS,
                    ),
                ],
            ),
            $connection
        );
    }
}

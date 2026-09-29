<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureStates;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Migration\Traits\StateMachineMigration;
use Shopwell\Core\Migration\Traits\StateMachineMigrationTrait;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

/**
 * @internal
 */
#[Package('framework')]
class Migration1643878976AddCaptureRefundStateMachines extends MigrationStep
{
    use StateMachineMigrationTrait;

    public function getCreationTimestamp(): int
    {
        return 1643878976;
    }

    public function update(Connection $connection): void
    {
        $this->import($this->captureStateMachine(), $connection);
        $this->import($this->captureRefundStateMachine(), $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function captureStateMachine(): StateMachineMigration
    {
        return new StateMachineMigration(
            OrderTransactionCaptureStates::STATE_MACHINE,
            '收款状态',
            'Capture state',
            [
                StateMachineMigration::state(
                    OrderTransactionCaptureStates::STATE_PENDING,
                    '待处理',
                    'Pending'
                ),
                StateMachineMigration::state(
                    OrderTransactionCaptureStates::STATE_COMPLETED,
                    '已完成',
                    'Complete'
                ),
                StateMachineMigration::state(
                    OrderTransactionCaptureStates::STATE_FAILED,
                    '已失败',
                    'Failed',
                ),
            ],
            [
                StateMachineMigration::transition(
                    StateMachineTransitionActions::ACTION_COMPLETE,
                    OrderTransactionCaptureStates::STATE_PENDING,
                    OrderTransactionCaptureStates::STATE_COMPLETED
                ),
                StateMachineMigration::transition(
                    StateMachineTransitionActions::ACTION_FAIL,
                    OrderTransactionCaptureStates::STATE_PENDING,
                    OrderTransactionCaptureStates::STATE_FAILED,
                ),
                StateMachineMigration::transition(
                    StateMachineTransitionActions::ACTION_REOPEN,
                    OrderTransactionCaptureStates::STATE_COMPLETED,
                    OrderTransactionCaptureStates::STATE_PENDING,
                ),
                StateMachineMigration::transition(
                    StateMachineTransitionActions::ACTION_REOPEN,
                    OrderTransactionCaptureStates::STATE_FAILED,
                    OrderTransactionCaptureStates::STATE_PENDING,
                ),
            ],
            OrderTransactionCaptureStates::STATE_PENDING
        );
    }

    private function captureRefundStateMachine(): StateMachineMigration
    {
        return new StateMachineMigration(
            OrderTransactionCaptureRefundStates::STATE_MACHINE,
            '退款状态',
            'Refund state',
            [
                StateMachineMigration::state(
                    OrderTransactionCaptureRefundStates::STATE_OPEN,
                    '待处理',
                    'Open'
                ),
                StateMachineMigration::state(
                    OrderTransactionCaptureRefundStates::STATE_IN_PROGRESS,
                    '处理中',
                    'In progress'
                ),
                StateMachineMigration::state(
                    OrderTransactionCaptureRefundStates::STATE_COMPLETED,
                    '已完成',
                    'Completed',
                ),
                StateMachineMigration::state(
                    OrderTransactionCaptureRefundStates::STATE_FAILED,
                    '已失败',
                    'Failed'
                ),
                StateMachineMigration::state(
                    OrderTransactionCaptureRefundStates::STATE_CANCELLED,
                    '已取消',
                    'Cancelled'
                ),
            ],
            [
                StateMachineMigration::transition(
                    StateMachineTransitionActions::ACTION_PROCESS,
                    OrderTransactionCaptureRefundStates::STATE_OPEN,
                    OrderTransactionCaptureRefundStates::STATE_IN_PROGRESS,
                ),
                StateMachineMigration::transition(
                    StateMachineTransitionActions::ACTION_CANCEL,
                    OrderTransactionCaptureRefundStates::STATE_OPEN,
                    OrderTransactionCaptureRefundStates::STATE_CANCELLED,
                ),
                StateMachineMigration::transition(
                    StateMachineTransitionActions::ACTION_FAIL,
                    OrderTransactionCaptureRefundStates::STATE_OPEN,
                    OrderTransactionCaptureRefundStates::STATE_FAILED,
                ),
                StateMachineMigration::transition(
                    StateMachineTransitionActions::ACTION_COMPLETE,
                    OrderTransactionCaptureRefundStates::STATE_OPEN,
                    OrderTransactionCaptureRefundStates::STATE_COMPLETED,
                ),
                StateMachineMigration::transition(
                    StateMachineTransitionActions::ACTION_CANCEL,
                    OrderTransactionCaptureRefundStates::STATE_IN_PROGRESS,
                    OrderTransactionCaptureRefundStates::STATE_CANCELLED,
                ),
                StateMachineMigration::transition(
                    StateMachineTransitionActions::ACTION_FAIL,
                    OrderTransactionCaptureRefundStates::STATE_IN_PROGRESS,
                    OrderTransactionCaptureRefundStates::STATE_FAILED,
                ),
                StateMachineMigration::transition(
                    StateMachineTransitionActions::ACTION_COMPLETE,
                    OrderTransactionCaptureRefundStates::STATE_IN_PROGRESS,
                    OrderTransactionCaptureRefundStates::STATE_COMPLETED,
                ),
                StateMachineMigration::transition(
                    StateMachineTransitionActions::ACTION_REOPEN,
                    OrderTransactionCaptureRefundStates::STATE_CANCELLED,
                    OrderTransactionCaptureRefundStates::STATE_OPEN,
                ),
                StateMachineMigration::transition(
                    StateMachineTransitionActions::ACTION_REOPEN,
                    OrderTransactionCaptureRefundStates::STATE_FAILED,
                    OrderTransactionCaptureRefundStates::STATE_OPEN,
                ),
                StateMachineMigration::transition(
                    StateMachineTransitionActions::ACTION_REOPEN,
                    OrderTransactionCaptureRefundStates::STATE_COMPLETED,
                    OrderTransactionCaptureRefundStates::STATE_OPEN,
                ),
            ],
            OrderTransactionCaptureRefundStates::STATE_OPEN
        );
    }
}

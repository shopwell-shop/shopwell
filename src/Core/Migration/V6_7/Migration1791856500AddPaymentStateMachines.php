<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Migration\Traits\StateMachineMigration;
use Shopwell\Core\Migration\Traits\StateMachineMigrationTrait;
use Shopwell\Core\System\Payment\PaymentOrderStates;
use Shopwell\Core\System\Payment\PaymentRefundStates;

/**
 * Adds the state machines of the generic payment gateway.
 *
 * It has to run before the tables are created, because their state fields reference these machines.
 *
 * @internal
 */
#[Package('framework')]
class Migration1791856500AddPaymentStateMachines extends MigrationStep
{
    use StateMachineMigrationTrait;

    public function getCreationTimestamp(): int
    {
        return 1791856500;
    }

    public function update(Connection $connection): void
    {
        $this->import(
            new StateMachineMigration(
                PaymentOrderStates::STATE_MACHINE,
                '支付单状态',
                'Payment order state',
                [
                    StateMachineMigration::state(PaymentOrderStates::STATE_CREATED, '已创建', 'Created'),
                    StateMachineMigration::state(PaymentOrderStates::STATE_OPENED, '已唤起支付', 'Opened'),
                    StateMachineMigration::state(PaymentOrderStates::STATE_PAID, '已支付', 'Paid'),
                    StateMachineMigration::state(PaymentOrderStates::STATE_FAILED, '支付失败', 'Failed'),
                    StateMachineMigration::state(PaymentOrderStates::STATE_CLOSED, '已关闭', 'Closed'),
                    StateMachineMigration::state(PaymentOrderStates::STATE_EXPIRED, '已过期', 'Expired'),
                    StateMachineMigration::state(PaymentOrderStates::STATE_REFUNDED_PARTIALLY, '部分退款', 'Partially refunded'),
                    StateMachineMigration::state(PaymentOrderStates::STATE_REFUNDED, '已全额退款', 'Refunded'),
                ],
                [
                    StateMachineMigration::transition(PaymentOrderStates::ACTION_PROCESS, PaymentOrderStates::STATE_CREATED, PaymentOrderStates::STATE_OPENED),
                    StateMachineMigration::transition(PaymentOrderStates::ACTION_PAY, PaymentOrderStates::STATE_OPENED, PaymentOrderStates::STATE_PAID),
                    StateMachineMigration::transition(PaymentOrderStates::ACTION_FAIL, PaymentOrderStates::STATE_OPENED, PaymentOrderStates::STATE_FAILED),
                    StateMachineMigration::transition(PaymentOrderStates::ACTION_CLOSE, PaymentOrderStates::STATE_CREATED, PaymentOrderStates::STATE_CLOSED),
                    StateMachineMigration::transition(PaymentOrderStates::ACTION_CLOSE, PaymentOrderStates::STATE_OPENED, PaymentOrderStates::STATE_CLOSED),
                    // an order the app never got pay parameters for may simply run out of time. One that
                    // was handed to the channel is never expired by the platform: the channel may report
                    // a late payment, and losing that payment would be worse than a lingering order.
                    StateMachineMigration::transition(PaymentOrderStates::ACTION_EXPIRE, PaymentOrderStates::STATE_CREATED, PaymentOrderStates::STATE_EXPIRED),
                    StateMachineMigration::transition(PaymentOrderStates::ACTION_EXPIRE, PaymentOrderStates::STATE_OPENED, PaymentOrderStates::STATE_EXPIRED),
                    StateMachineMigration::transition(PaymentOrderStates::ACTION_REFUND_PARTIALLY, PaymentOrderStates::STATE_PAID, PaymentOrderStates::STATE_REFUNDED_PARTIALLY),
                    StateMachineMigration::transition(PaymentOrderStates::ACTION_REFUND_PARTIALLY, PaymentOrderStates::STATE_REFUNDED_PARTIALLY, PaymentOrderStates::STATE_REFUNDED_PARTIALLY),
                    StateMachineMigration::transition(PaymentOrderStates::ACTION_REFUND, PaymentOrderStates::STATE_PAID, PaymentOrderStates::STATE_REFUNDED),
                    StateMachineMigration::transition(PaymentOrderStates::ACTION_REFUND, PaymentOrderStates::STATE_REFUNDED_PARTIALLY, PaymentOrderStates::STATE_REFUNDED),
                ],
                PaymentOrderStates::STATE_CREATED,
            ),
            $connection
        );

        $this->import(
            new StateMachineMigration(
                PaymentRefundStates::STATE_MACHINE,
                '支付退款状态',
                'Payment refund state',
                [
                    StateMachineMigration::state(PaymentRefundStates::STATE_OPEN, '已申请', 'Open'),
                    StateMachineMigration::state(PaymentRefundStates::STATE_PROCESSING, '退款中', 'Processing'),
                    StateMachineMigration::state(PaymentRefundStates::STATE_FINISHED, '退款成功', 'Finished'),
                    StateMachineMigration::state(PaymentRefundStates::STATE_FAILED, '退款失败', 'Failed'),
                ],
                [
                    StateMachineMigration::transition(PaymentRefundStates::ACTION_PROCESS, PaymentRefundStates::STATE_OPEN, PaymentRefundStates::STATE_PROCESSING),
                    StateMachineMigration::transition(PaymentRefundStates::ACTION_FINISH, PaymentRefundStates::STATE_PROCESSING, PaymentRefundStates::STATE_FINISHED),
                    StateMachineMigration::transition(PaymentRefundStates::ACTION_FINISH, PaymentRefundStates::STATE_OPEN, PaymentRefundStates::STATE_FINISHED),
                    StateMachineMigration::transition(PaymentRefundStates::ACTION_FAIL, PaymentRefundStates::STATE_PROCESSING, PaymentRefundStates::STATE_FAILED),
                    StateMachineMigration::transition(PaymentRefundStates::ACTION_FAIL, PaymentRefundStates::STATE_OPEN, PaymentRefundStates::STATE_FAILED),
                ],
                PaymentRefundStates::STATE_OPEN,
            ),
            $connection
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do here
    }
}

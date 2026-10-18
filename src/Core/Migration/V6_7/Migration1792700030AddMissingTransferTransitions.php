<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Migration\Traits\StateMachineMigration;
use Shopwell\Core\Migration\Traits\StateMachineMigrationTrait;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;

/**
 * The `StateMachineMigration` importer is add-only - it never deletes or rewrites existing rows -
 * which is why only the three new transitions are passed in here and the 15 existing ones are not
 * repeated.
 *
 * `created -> failed` has to exist because the interfaces refuse a payout outright without ever
 * creating an object (alipay answers `code != 10000`, stripe rejects the transfer call), so a
 * refusal leaves no provider reference to record and `submit` is not an available route out of
 * `created`.
 *
 * `created -> canceled` has to exist because a never-submitted transfer must be abandonable, or
 * refusals pile up as dead rows.
 *
 * `processing -> returned` has to exist because paypal's item goes from `UNCLAIMED` (our
 * `processing`) straight to `RETURNED` after 30 days unclaimed, never passing `SUCCESS`, and the
 * machine only offers `mark_returned` from `succeeded` so far - the first such notification would
 * be an `IllegalTransitionException`.
 *
 * @internal
 */
#[Package('framework')]
class Migration1792700030AddMissingTransferTransitions extends MigrationStep
{
    use StateMachineMigrationTrait;

    public function getCreationTimestamp(): int
    {
        return 1792700030;
    }

    public function update(Connection $connection): void
    {
        $this->import(
            new StateMachineMigration(
                PaymentTransferStates::STATE_MACHINE,
                '打款单状态',
                'Payment transfer status',
                [
                    StateMachineMigration::state(PaymentTransferStates::STATE_CREATED, '已创建', 'Created'),
                    StateMachineMigration::state(PaymentTransferStates::STATE_SUBMITTED, '已提交', 'Submitted'),
                    StateMachineMigration::state(PaymentTransferStates::STATE_AWAITING_USER_CONFIRM, '待用户确认', 'Awaiting user confirmation'),
                    StateMachineMigration::state(PaymentTransferStates::STATE_PROCESSING, '处理中', 'Processing'),
                    StateMachineMigration::state(PaymentTransferStates::STATE_SUCCEEDED, '打款成功', 'Succeeded'),
                    StateMachineMigration::state(PaymentTransferStates::STATE_FAILED, '打款失败', 'Failed'),
                    StateMachineMigration::state(PaymentTransferStates::STATE_CANCELED, '已撤销', 'Canceled'),
                    StateMachineMigration::state(PaymentTransferStates::STATE_RETURNED, '已退回', 'Returned'),
                ],
                [
                    StateMachineMigration::transition(PaymentTransferStates::ACTION_FAIL, PaymentTransferStates::STATE_CREATED, PaymentTransferStates::STATE_FAILED),
                    StateMachineMigration::transition(PaymentTransferStates::ACTION_CANCEL, PaymentTransferStates::STATE_CREATED, PaymentTransferStates::STATE_CANCELED),
                    StateMachineMigration::transition(PaymentTransferStates::ACTION_MARK_RETURNED, PaymentTransferStates::STATE_PROCESSING, PaymentTransferStates::STATE_RETURNED),
                ],
                PaymentTransferStates::STATE_CREATED,
            ),
            $connection
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do here
    }
}

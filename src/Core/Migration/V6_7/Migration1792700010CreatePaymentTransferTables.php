<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Migration\Traits\StateMachineMigration;
use Shopwell\Core\Migration\Traits\StateMachineMigrationTrait;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;

/**
 * Creates the model of a payout of the platform to a recipient: the recipient itself and one transfer
 * per request to the interface.
 *
 * A recipient is a separate row because it is a separate thing: the interfaces disagree on what they
 * need before the first payout (stripe an onboarded account, wechat an openid of this very app, paypal
 * only an email until the money is claimed), so the recipient carries both maturities and the transfer
 * only references it.
 *
 * The state machine carries the two edges that look counterintuitive but are forced by the interfaces:
 * `succeeded -> failed` (stripe reports `paid` and changes it to `failed` within five business days)
 * and `succeeded -> returned` (alipay refunds a transfer to a card the bank refused, paypal returns a
 * payout nobody claimed within 30 days). Without them the first such notification would be an illegal
 * transition.
 *
 * Every action resolves to exactly one destination per origin state - the state machine registry
 * looks the destination up by (machine, action, from state) and refuses an ambiguous action.
 *
 * @internal
 */
#[Package('framework')]
class Migration1792700010CreatePaymentTransferTables extends MigrationStep
{
    use StateMachineMigrationTrait;

    public function getCreationTimestamp(): int
    {
        return 1792700010;
    }

    public function update(Connection $connection): void
    {
        // the status field of the transfer references the states, so the machine comes first
        $this->importPaymentTransferStateMachine($connection);

        $this->createPaymentTransferRecipientTable($connection);
        $this->createPaymentTransferTable($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do here
    }

    private function importPaymentTransferStateMachine(Connection $connection): void
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
                    StateMachineMigration::transition(PaymentTransferStates::ACTION_SUBMIT, PaymentTransferStates::STATE_CREATED, PaymentTransferStates::STATE_SUBMITTED),
                    StateMachineMigration::transition(PaymentTransferStates::ACTION_PROCESS, PaymentTransferStates::STATE_SUBMITTED, PaymentTransferStates::STATE_PROCESSING),
                    StateMachineMigration::transition(PaymentTransferStates::ACTION_PROCESS, PaymentTransferStates::STATE_AWAITING_USER_CONFIRM, PaymentTransferStates::STATE_PROCESSING),
                    StateMachineMigration::transition(PaymentTransferStates::ACTION_AWAIT_CONFIRM, PaymentTransferStates::STATE_SUBMITTED, PaymentTransferStates::STATE_AWAITING_USER_CONFIRM),
                    // alipay may answer a submission with the final status, so `submitted` succeeds directly
                    StateMachineMigration::transition(PaymentTransferStates::ACTION_SUCCEED, PaymentTransferStates::STATE_SUBMITTED, PaymentTransferStates::STATE_SUCCEEDED),
                    StateMachineMigration::transition(PaymentTransferStates::ACTION_SUCCEED, PaymentTransferStates::STATE_AWAITING_USER_CONFIRM, PaymentTransferStates::STATE_SUCCEEDED),
                    StateMachineMigration::transition(PaymentTransferStates::ACTION_SUCCEED, PaymentTransferStates::STATE_PROCESSING, PaymentTransferStates::STATE_SUCCEEDED),
                    StateMachineMigration::transition(PaymentTransferStates::ACTION_FAIL, PaymentTransferStates::STATE_SUBMITTED, PaymentTransferStates::STATE_FAILED),
                    StateMachineMigration::transition(PaymentTransferStates::ACTION_FAIL, PaymentTransferStates::STATE_AWAITING_USER_CONFIRM, PaymentTransferStates::STATE_FAILED),
                    StateMachineMigration::transition(PaymentTransferStates::ACTION_FAIL, PaymentTransferStates::STATE_PROCESSING, PaymentTransferStates::STATE_FAILED),
                    // stripe `payout.paid` is not final: it may turn into `failed` within five business days
                    StateMachineMigration::transition(PaymentTransferStates::ACTION_FAIL, PaymentTransferStates::STATE_SUCCEEDED, PaymentTransferStates::STATE_FAILED),
                    StateMachineMigration::transition(PaymentTransferStates::ACTION_CANCEL, PaymentTransferStates::STATE_SUBMITTED, PaymentTransferStates::STATE_CANCELED),
                    StateMachineMigration::transition(PaymentTransferStates::ACTION_CANCEL, PaymentTransferStates::STATE_AWAITING_USER_CONFIRM, PaymentTransferStates::STATE_CANCELED),
                    // wechat cancels a running transfer: `CANCELING` -> `CANCELLED`
                    StateMachineMigration::transition(PaymentTransferStates::ACTION_CANCEL, PaymentTransferStates::STATE_PROCESSING, PaymentTransferStates::STATE_CANCELED),
                    // alipay's card transfer can be refunded by the bank, paypal returns an unclaimed payout
                    StateMachineMigration::transition(PaymentTransferStates::ACTION_MARK_RETURNED, PaymentTransferStates::STATE_SUCCEEDED, PaymentTransferStates::STATE_RETURNED),
                ],
                PaymentTransferStates::STATE_CREATED,
            ),
            $connection
        );
    }

    /**
     * The recipient is scoped to the app: the alipay uid of one person is a different recipient under
     * a different app, so the identity key spans the app. Both references are restricted - an app or a
     * recipient that still has transfers can not be deleted without losing the payout history.
     *
     * `onboarding_status` carries no ddl default on purpose: it is written explicitly by whoever
     * creates the recipient ('unregistered'), and a default would be a second, silently diverging
     * source of truth that every `dal:migration:create` run would rewrite. `payout_enabled` carries
     * the `0` the dal emits for every boolean column - any other value would drift.
     */
    private function createPaymentTransferRecipientTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_transfer_recipient` (
                `id` BINARY(16) NOT NULL,
                `payment_app_id` BINARY(16) NOT NULL,
                `provider` VARCHAR(64) NOT NULL,
                `identity_type` VARCHAR(64) NOT NULL,
                `identity_value` VARCHAR(255) NOT NULL,
                `holder_name` VARCHAR(255) NULL,
                `provider_account_id` VARCHAR(255) NULL,
                `bank_info` JSON NULL,
                `onboarding_status` VARCHAR(32) NOT NULL,
                `payout_enabled` TINYINT(1) NOT NULL DEFAULT 0,
                `verified_at` DATETIME(3) NULL,
                `metadata` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_transfer_recipient.identity` (`payment_app_id`, `provider`, `identity_type`, `identity_value`),
                KEY `idx.payment_transfer_recipient.payment_app_id` (`payment_app_id`),
                CONSTRAINT `fk.payment_transfer_recipient.payment_app_id` FOREIGN KEY (`payment_app_id`) REFERENCES `payment_app` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * One payout: one recipient, one request. The out number is the idempotency key of the business
     * case and is unique per app - it is reused by every retry, a second number would be a second
     * payout. The transfer number is the display number and unique on its own.
     *
     * `submit_attempts` carries no ddl default for the same reason `onboarding_status` carries none -
     * the writer spells `0` out.
     */
    private function createPaymentTransferTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_transfer` (
                `id` BINARY(16) NOT NULL,
                `payment_app_id` BINARY(16) NOT NULL,
                `transfer_no` VARCHAR(64) NOT NULL,
                `out_no` VARCHAR(64) NOT NULL,
                `recipient_id` BINARY(16) NOT NULL,
                `provider` VARCHAR(64) NOT NULL,
                `channel` VARCHAR(64) NOT NULL,
                `amount` INT NOT NULL,
                `currency` VARCHAR(3) NOT NULL,
                `status_id` BINARY(16) NOT NULL,
                `provider_ref` VARCHAR(255) NULL,
                `provider_stage_ref` VARCHAR(255) NULL,
                `confirm_payload` JSON NULL,
                `failure_code` VARCHAR(128) NULL,
                `failure_reason` VARCHAR(255) NULL,
                `submit_attempts` INT NOT NULL,
                `expire_at` DATETIME(3) NULL,
                `submitted_at` DATETIME(3) NULL,
                `settled_at` DATETIME(3) NULL,
                `returned_at` DATETIME(3) NULL,
                `canceled_at` DATETIME(3) NULL,
                `metadata` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_transfer.transfer_no` (`transfer_no`),
                UNIQUE KEY `uniq.payment_transfer.out_no` (`payment_app_id`, `out_no`),
                KEY `idx.payment_transfer.status_id` (`status_id`),
                KEY `idx.payment_transfer.recipient_id` (`recipient_id`),
                CONSTRAINT `fk.payment_transfer.payment_app_id` FOREIGN KEY (`payment_app_id`) REFERENCES `payment_app` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.payment_transfer.recipient_id` FOREIGN KEY (`recipient_id`) REFERENCES `payment_transfer_recipient` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.payment_transfer.status_id` FOREIGN KEY (`status_id`) REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }
}

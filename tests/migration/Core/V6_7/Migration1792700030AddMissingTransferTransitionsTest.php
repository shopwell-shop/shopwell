<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Migration\V6_7\Migration1792700010CreatePaymentTransferTables;
use Shopwell\Core\Migration\V6_7\Migration1792700030AddMissingTransferTransitions;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1792700030AddMissingTransferTransitions::class)]
class Migration1792700030AddMissingTransferTransitionsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1792700030, (new Migration1792700030AddMissingTransferTransitions())->getCreationTimestamp());
    }

    /**
     * The interfaces refuse a payout without creating an object, so a refusal leaves no reference
     * to record and `submit` is not an available route out of `created` - the refusal has to be
     * bookable as `failed`, and a never-submitted transfer has to be abandonable. Paypal's item
     * goes from `UNCLAIMED` (our `processing`) straight to `RETURNED`, never passing `SUCCESS`,
     * so `mark_returned` has to be available from `processing` as well.
     */
    public function testTheThreeMissingEdgesExist(): void
    {
        $this->importChain();
        $this->runMigration();

        static::assertTrue($this->transitionExists(PaymentTransferStates::ACTION_FAIL, PaymentTransferStates::STATE_CREATED, PaymentTransferStates::STATE_FAILED));
        static::assertTrue($this->transitionExists(PaymentTransferStates::ACTION_CANCEL, PaymentTransferStates::STATE_CREATED, PaymentTransferStates::STATE_CANCELED));
        static::assertTrue($this->transitionExists(PaymentTransferStates::ACTION_MARK_RETURNED, PaymentTransferStates::STATE_PROCESSING, PaymentTransferStates::STATE_RETURNED));
    }

    public function testTheMachineHasEighteenTransitionsAfterRunning(): void
    {
        $this->importChain();
        $this->runMigration();

        static::assertSame(18, $this->countTransitions());
    }

    /**
     * The `StateMachineMigration` importer is add-only, so a second run must not duplicate the
     * edges - proven here rather than assumed.
     */
    public function testRunningTheMigrationTwiceLeavesEighteenTransitions(): void
    {
        $this->importChain();
        $this->runMigration();
        $this->runMigration();

        static::assertSame(18, $this->countTransitions());

        foreach ([
            [PaymentTransferStates::ACTION_FAIL, PaymentTransferStates::STATE_CREATED, PaymentTransferStates::STATE_FAILED],
            [PaymentTransferStates::ACTION_MARK_RETURNED, PaymentTransferStates::STATE_PROCESSING, PaymentTransferStates::STATE_RETURNED],
        ] as [$action, $from, $to]) {
            static::assertSame(1, (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM `state_machine_transition` `transition`
                    INNER JOIN `state_machine_state` `from_state` ON `from_state`.`id` = `transition`.`from_state_id`
                    INNER JOIN `state_machine_state` `to_state` ON `to_state`.`id` = `transition`.`to_state_id`
                    INNER JOIN `state_machine` `machine` ON `machine`.`id` = `transition`.`state_machine_id`
                    WHERE `machine`.`technical_name` = :machine
                        AND `transition`.`action_name` = :action
                        AND `from_state`.`technical_name` = :from
                        AND `to_state`.`technical_name` = :to',
                ['machine' => PaymentTransferStates::STATE_MACHINE, 'action' => $action, 'from' => $from, 'to' => $to]
            ), $action . ' ' . $from . '->' . $to . ' must not be duplicated by a second run');
        }
    }

    /**
     * An action must resolve to exactly one destination state per origin: the registry looks the
     * destination up by (machine, action, from state) and refuses an ambiguous action.
     */
    public function testAnActionNeverHasTwoDestinationsFromOneState(): void
    {
        $this->importChain();
        $this->runMigration();

        $ambiguous = $this->connection->fetchAllAssociative(
            'SELECT `machine`.`technical_name` AS machine,
                    `transition`.`action_name` AS action,
                    `transition`.`from_state_id` AS from_state,
                    COUNT(DISTINCT `transition`.`to_state_id`) AS destinations
                FROM `state_machine_transition` `transition`
                INNER JOIN `state_machine` `machine` ON `machine`.`id` = `transition`.`state_machine_id`
                WHERE `machine`.`technical_name` = :machine
                GROUP BY `machine`.`technical_name`, `transition`.`action_name`, `transition`.`from_state_id`
                HAVING destinations > 1',
            ['machine' => PaymentTransferStates::STATE_MACHINE]
        );

        static::assertSame([], $ambiguous, 'a state machine action must resolve to exactly one destination state');
    }

    /**
     * The 15 transitions come from the migration that creates the machine, so this test runs that
     * chain explicitly: relying on the kernel bootstrap to have applied it would test the
     * environment instead of the migration under test.
     *
     * Before the chain, the transitions of this machine are reset - the kernel bootstrap runs the
     * whole migration chain against the test database first, and only the transitions of this very
     * machine may be deleted, the other machines in this database (order, document, ...) must stay
     * untouched. Only the transitions are reset, not the states: the state definitions are
     * add-only and idempotent, re-importing them inserts nothing.
     */
    private function importChain(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `state_machine_transition` WHERE `state_machine_id` = (
                SELECT `id` FROM `state_machine` WHERE `technical_name` = :machine
            )',
            ['machine' => PaymentTransferStates::STATE_MACHINE]
        );

        (new Migration1792700010CreatePaymentTransferTables())->update($this->connection);
    }

    private function runMigration(): void
    {
        (new Migration1792700030AddMissingTransferTransitions())->update($this->connection);
    }

    private function transitionExists(string $action, string $from, string $to): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `state_machine_transition` `transition`
                INNER JOIN `state_machine_state` `from_state` ON `from_state`.`id` = `transition`.`from_state_id`
                INNER JOIN `state_machine_state` `to_state` ON `to_state`.`id` = `transition`.`to_state_id`
                INNER JOIN `state_machine` `machine` ON `machine`.`id` = `transition`.`state_machine_id`
                WHERE `machine`.`technical_name` = :machine
                    AND `transition`.`action_name` = :action
                    AND `from_state`.`technical_name` = :from
                    AND `to_state`.`technical_name` = :to',
            ['machine' => PaymentTransferStates::STATE_MACHINE, 'action' => $action, 'from' => $from, 'to' => $to]
        );

        return (int) $count > 0;
    }

    private function countTransitions(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `state_machine_transition` `transition`
                INNER JOIN `state_machine` `machine` ON `machine`.`id` = `transition`.`state_machine_id`
                WHERE `machine`.`technical_name` = :machine',
            ['machine' => PaymentTransferStates::STATE_MACHINE]
        );
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * Makes the identity of a transfer recipient storable in encrypted form without losing its uniqueness.
 *
 * The identity value of a recipient is a credential: a bank card number, an openid, an account id. It
 * is therefore encrypted at rest exactly like the app secret and the channel credentials
 * (see PaymentCredentialCipher), and a ciphertext can never be part of a unique key - the same plain
 * text encrypts to a different ciphertext every time, so storing one recipient twice would produce two
 * rows that look different.
 *
 * The hash is the deterministic half of the pair: `sha256(payment_app_id|provider|identity_type|identity_value)`
 * over the plain text, computed by the writer ({@see \Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientService}).
 * It is what the unique key is built on, and it doubles as the lookup for "which payouts went to this
 * identity" because it can be computed from a plain text without decrypting the table.
 *
 * The table was created together with the transfer model and carries no rows yet, so nothing is
 * backfilled: a row could only be hashed from its plain text, which by definition no longer exists in
 * the database. An installation that somehow has rows has to re-import them.
 *
 * @internal
 */
#[Package('framework')]
class Migration1792700020AddTransferRecipientIdentityHash extends MigrationStep
{
    private const TABLE = 'payment_transfer_recipient';

    private const UNIQUE_KEY = 'uniq.payment_transfer_recipient.identity';

    private const IDENTITY_INDEX = 'idx.payment_transfer_recipient.identity_hash';

    /**
     * The unique key over the identity value doubles as the supporting index of the foreign key to
     * `payment_app` - its leftmost column is `payment_app_id`. MySQL refuses to drop it while it is
     * the only index the constraint can use (error 1553), so the table gets an index of its own for
     * the app before the unique key is rebuilt.
     */
    private const APP_INDEX = 'idx.payment_transfer_recipient.payment_app_id';

    public function getCreationTimestamp(): int
    {
        return 1792700020;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, self::TABLE, 'identity_hash')) {
            $connection->executeStatement(
                'ALTER TABLE `' . self::TABLE . '`
                    ADD COLUMN `identity_hash` VARCHAR(64) NOT NULL AFTER `identity_value`'
            );
        }

        if (!TableHelper::indexExists($connection, self::TABLE, self::APP_INDEX)) {
            $connection->executeStatement(
                'ALTER TABLE `' . self::TABLE . '` ADD KEY `' . self::APP_INDEX . '` (`payment_app_id`)'
            );
        }

        // the unique key moves from the identity value to its hash: the value is encrypted from now
        // on, the hash is the deterministic half that still identifies the recipient
        if (!TableHelper::indexSpansColumns(
            $connection,
            self::TABLE,
            self::UNIQUE_KEY,
            ['payment_app_id', 'provider', 'identity_type', 'identity_hash']
        )) {
            if (TableHelper::indexExists($connection, self::TABLE, self::UNIQUE_KEY)) {
                $connection->executeStatement('ALTER TABLE `' . self::TABLE . '` DROP INDEX `' . self::UNIQUE_KEY . '`');
            }

            $connection->executeStatement(
                'ALTER TABLE `' . self::TABLE . '`
                    ADD UNIQUE KEY `' . self::UNIQUE_KEY . '` (`payment_app_id`, `provider`, `identity_type`, `identity_hash`)'
            );
        }

        // the hash is also the only way back from "who received this payout" to the recipient, since
        // the value itself can not be searched in an encrypted column
        if (!TableHelper::indexExists($connection, self::TABLE, self::IDENTITY_INDEX)) {
            $connection->executeStatement(
                'ALTER TABLE `' . self::TABLE . '` ADD KEY `' . self::IDENTITY_INDEX . '` (`identity_hash`)'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do here
    }
}

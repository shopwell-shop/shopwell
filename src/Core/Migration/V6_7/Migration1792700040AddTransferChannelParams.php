<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * Keeps the parameters a payout is built from with the payout itself.
 *
 * The parameters of a channel are not configuration and not derivable: alipay demands the
 * `transfer_scene_name` it was applied for and the per-case `transfer_scene_report_infos`, and the
 * business case decides both when it files the payout. Until now they only lived in the request of
 * the caller, so they were gone the moment the call returned `created`.
 *
 * That is a functional hole rather than a matter of taste. A transfer is submitted later and may be
 * sent again days afterwards - the channel answered `SYSTEM_ERROR`, the query task found nothing, the
 * process that created the payout no longer exists. Nothing at that moment can ask the caller for the
 * scene it wanted, and an adapter without the scene refuses to build the request
 * ({@see \Shopwell\Core\System\Payment\Provider\Alipay\AlipayTransferAction::contentOf()}). The very
 * same reasoning makes `payment_order.channel_params` durable in `PaymentOrderCapture::capture()`: a
 * retry is charged with the parameters of the first attempt, not with new ones.
 *
 * Nullable on purpose: the parameters are optional for every interface except alipay, and an empty
 * map says nothing that `NULL` does not say as well.
 *
 * @internal
 */
#[Package('framework')]
class Migration1792700040AddTransferChannelParams extends MigrationStep
{
    private const TABLE = 'payment_transfer';

    public function getCreationTimestamp(): int
    {
        return 1792700040;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, self::TABLE, 'channel_params')) {
            return;
        }

        $connection->executeStatement(
            'ALTER TABLE `' . self::TABLE . '`
                ADD COLUMN `channel_params` JSON NULL AFTER `currency`'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do here
    }
}

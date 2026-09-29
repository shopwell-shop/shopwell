<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1784276129RepairPasswordChangedMailTranslations extends MigrationStep
{
    private const WRONG_NAME = '客户密码已更改';

    private const CORRECT_NAME = '客户密码已修改';

    public function getCreationTimestamp(): int
    {
        return 1784276129;
    }

    public function update(Connection $connection): void
    {
        // The broken literal only ever originated from Migration1763377570, so every
        // occurrence is incorrect - including copies in other languages or templates.
        $connection->executeStatement(
            'UPDATE `mail_template_type_translation` SET `name` = :correctName WHERE `name` = :wrongName',
            [
                'wrongName' => self::WRONG_NAME,
                'correctName' => self::CORRECT_NAME,
            ],
        );

        $connection->executeStatement(
            'UPDATE `mail_template_translation` SET `subject` = :correctSubject WHERE `subject` = :wrongSubject',
            [
                'wrongSubject' => self::WRONG_NAME,
                'correctSubject' => self::CORRECT_NAME,
            ],
        );
    }
}

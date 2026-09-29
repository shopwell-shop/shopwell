<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\Traits\ImportTranslationsTrait;

/**
 * @internal
 */
#[Package('framework')]
class Migration1711461579FixDefaultMailFooter extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1711461579;
    }

    public function update(Connection $connection): void
    {
        $languages = $this->getLanguageIds($connection, 'zh-CN');
        if (!$languages) {
            return;
        }

        // The default footer was originally shipped with a wrong character variant
        // for the bank account label and has to be corrected for existing shops.
        $connection->executeStatement(
            'UPDATE mail_header_footer_translation
            SET footer_plain = REPLACE(footer_plain, \'银行帐户\', \'银行账户\')
            WHERE language_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($languages)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }
}

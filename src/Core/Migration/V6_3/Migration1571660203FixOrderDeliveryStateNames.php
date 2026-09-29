<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1571660203FixOrderDeliveryStateNames extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1571660203;
    }

    public function update(Connection $connection): void
    {
        $defaultLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $zhCnLangId = $this->getLanguageIdByLocale($connection, 'zh-CN');

        foreach ($this->getMailTemplatesMapping() as $technicalName => $mailTemplate) {
            if ($defaultLangId !== $zhCnLangId) {
                $sql = <<<'SQL'
                UPDATE `mail_template_type_translation` SET `name` = :name
                    WHERE `mail_template_type_id` = (SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName)
                      AND `language_id` = :lang
SQL;

                $connection->executeStatement($sql, ['name' => $mailTemplate['name'], 'technicalName' => $technicalName, 'lang' => $defaultLangId]);
            }

            if ($defaultLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
                $sql = <<<'SQL'
                UPDATE `mail_template_type_translation` SET `name` = :name
                    WHERE `mail_template_type_id` = (SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName)
                      AND `language_id` = :lang
SQL;

                $connection->executeStatement($sql, ['name' => $mailTemplate['name'], 'technicalName' => $technicalName, 'lang' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]);
            }

            if ($zhCnLangId) {
                $sql = <<<'SQL'
                UPDATE `mail_template_type_translation` SET `name` = :name
                    WHERE `mail_template_type_id` = (SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName)
                      AND `language_id` = :lang
SQL;

                $connection->executeStatement($sql, ['name' => $mailTemplate['nameZh'], 'technicalName' => $technicalName, 'lang' => $zhCnLangId]);
            }
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    /**
     * @return array<string, array{name: string, nameZh: string}>
     */
    private function getMailTemplatesMapping(): array
    {
        return [
            'state_enter.order_delivery.state.returned_partially' => [
                'name' => 'Enter delivery state: Open',
                'nameZh' => '进入配送状态：待处理',
            ],
            'state_enter.order_delivery.state.shipped_partially' => [
                'name' => 'Enter delivery state: Shipped (partially)',
                'nameZh' => '进入配送状态：部分发货',
            ],
            'state_enter.order_delivery.state.returned' => [
                'name' => 'Enter delivery state: Returned',
                'nameZh' => '进入配送状态：退货',
            ],
            'state_enter.order_delivery.state.shipped' => [
                'name' => 'Enter delivery state: Shipped',
                'nameZh' => '进入配送状态：已发货',
            ],
            'state_enter.order_delivery.state.cancelled' => [
                'name' => 'Enter delivery state: Cancelled',
                'nameZh' => '进入配送状态：已取消',
            ],
        ];
    }

    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $sql = <<<'SQL'
SELECT `language`.`id`
FROM `language`
INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
WHERE `locale`.`code` = :code
SQL;

        $languageId = $connection->executeQuery($sql, ['code' => $locale])->fetchOne();
        if (!$languageId && $locale !== 'en-GB') {
            return null;
        }

        if (!$languageId) {
            return Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        }

        return $languageId;
    }
}

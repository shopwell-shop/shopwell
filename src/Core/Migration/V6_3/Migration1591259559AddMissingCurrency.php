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
class Migration1591259559AddMissingCurrency extends MigrationStep
{
    private ?string $zhCnLanguage = null;

    private ?string $enLanguage = null;

    public function getCreationTimestamp(): int
    {
        return 1591259559;
    }

    public function update(Connection $connection): void
    {
        if ($this->currencyExists($connection, 'CZK')) {
            return;
        }

        $this->addCurrency($connection, Uuid::randomBytes(), 'CZK', 26.735, 'Kč', 'CZK', 'CZK', '捷克克朗', 'Czech koruna');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function addCurrency(
        Connection $connection,
        string $id,
        string $isoCode,
        float $factor,
        string $symbol,
        string $shortNameZh,
        string $shortNameEn,
        string $nameZh,
        string $nameEn
    ): void {
        $languageDefault = $this->getEnLanguageId($connection);
        $languageZhCn = $this->getZhCnLanguageId($connection);

        $langId = $connection->fetchOne('
        SELECT `currency`.`id` FROM `currency` WHERE `iso_code` = :code LIMIT 1
        ', ['code' => $isoCode]);

        if (!$langId) {
            $connection->insert('currency', ['id' => $id, 'iso_code' => $isoCode, 'factor' => $factor, 'symbol' => $symbol, 'position' => 1, 'decimal_precision' => 2, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
            if ($languageDefault !== $languageZhCn) {
                $connection->insert('currency_translation', ['currency_id' => $id, 'language_id' => $languageDefault, 'short_name' => $shortNameEn, 'name' => $nameEn, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
            }
            if ($languageZhCn) {
                $connection->insert('currency_translation', ['currency_id' => $id, 'language_id' => $languageZhCn, 'short_name' => $shortNameZh, 'name' => $nameZh, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
            }
        }
    }

    private function getZhCnLanguageId(Connection $connection): ?string
    {
        if (!$this->zhCnLanguage) {
            $this->zhCnLanguage = $this->fetchLanguageId('zh-CN', $connection);
        }

        return $this->zhCnLanguage;
    }

    private function getEnLanguageId(Connection $connection): ?string
    {
        if (!$this->enLanguage) {
            $this->enLanguage = $this->fetchLanguageId('en-GB', $connection);
        }

        return $this->enLanguage;
    }

    private function fetchLanguageId(string $code, Connection $connection): ?string
    {
        $langId = $connection->fetchOne('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`translation_code_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => $code]);

        if (!$langId && $code !== 'en-GB') {
            return null;
        }

        if (!$langId) {
            return Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        }

        return $langId;
    }

    private function currencyExists(Connection $connection, string $isoCode): bool
    {
        return (bool) $connection->fetchOne('SELECT * FROM currency WHERE LOWER(iso_code) = LOWER(?)', [$isoCode]);
    }
}

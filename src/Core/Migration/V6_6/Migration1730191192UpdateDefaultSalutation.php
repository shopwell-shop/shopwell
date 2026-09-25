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
class Migration1730191192UpdateDefaultSalutation extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1730191192;
    }

    public function update(Connection $connection): void
    {
        $salutationId = $connection->fetchOne('SELECT id FROM salutation WHERE salutation_key = "not_specified"');

        if (!$salutationId) {
            return;
        }

        $this->updateSalutationForLanguage($connection, $salutationId, 'en-GB', 'Dear');
        $this->updateSalutationForLanguage($connection, $salutationId, 'de-DE', 'Guten Tag');
    }

    private function updateSalutationForLanguage(Connection $connection, string $salutationId, string $locale, string $letterName): void
    {
        $languageIds = $this->getLanguageIds($connection, $locale);
        if (!$languageIds) {
            return;
        }

        $this->updateSalutation($connection, $salutationId, $languageIds, $letterName);
    }

    /**
     * @param array<string> $languageIds
     */
    private function updateSalutation(Connection $connection, string $salutationId, array $languageIds, string $letterName): void
    {
        $connection->executeStatement('UPDATE salutation_translation SET letter_name = :letterName WHERE salutation_id = :salutationId AND language_id IN (:languageIds) AND updated_at IS NULL', [
            'letterName' => $letterName,
            'salutationId' => $salutationId,
            'languageIds' => Uuid::fromHexToBytesList($languageIds),
        ], ['languageIds' => ArrayParameterType::BINARY]);
    }
}

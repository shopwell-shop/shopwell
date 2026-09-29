<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1768545322AssignRevocationPageToSystemConfigSetting extends MigrationStep
{
    final public const REVOCATION_PAGE_CONFIG_KEY = 'core.basicInformation.revocationRequestPage';
    final public const REVOCATION_BUTTON_CONFIG_KEY = 'core.basicInformation.showRevocationButton';

    public function getCreationTimestamp(): int
    {
        return 1768545322;
    }

    public function update(Connection $connection): void
    {
        if ($this->isPageAssigned($connection)) {
            return;
        }

        $pageByteId = $this->getPageId($connection);
        if ($pageByteId === null) {
            return;
        }

        $this->linkPage($connection, $pageByteId);
        $this->disableButton($connection);
    }

    private function disableButton(Connection $connection): void
    {
        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => self::REVOCATION_BUTTON_CONFIG_KEY,
            'configuration_value' => '{"_value": false}',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function linkPage(Connection $connection, string $pageByteId): void
    {
        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => self::REVOCATION_PAGE_CONFIG_KEY,
            'configuration_value' => \sprintf('{"_value": "%s"}', Uuid::fromBytesToHex($pageByteId)),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function getPageId(Connection $connection): ?string
    {
        $pageByteId = $connection->fetchOne(
            'SELECT cms_page_id FROM cms_page_translation WHERE (name = :enName OR name = :zhName) AND cms_page_version_id = :versionId',
            [
                'zhName' => Migration1768545320RevocationRequestCmsForm::CMS_PAGE_TRANSLATIONS['zh_name'],
                'enName' => Migration1768545320RevocationRequestCmsForm::CMS_PAGE_TRANSLATIONS['en_name'],
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ]
        );

        if (!\is_string($pageByteId)) {
            return null;
        }

        return $pageByteId;
    }

    private function isPageAssigned(Connection $connection): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT 1 FROM system_config WHERE configuration_key = :configKey LIMIT 1',
            ['configKey' => self::REVOCATION_PAGE_CONFIG_KEY]
        );
    }
}

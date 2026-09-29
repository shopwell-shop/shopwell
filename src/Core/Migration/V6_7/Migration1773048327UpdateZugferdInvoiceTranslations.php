<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Checkout\Document\Renderer\ZugferdEmbeddedRenderer;
use Shopwell\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Migration\Traits\ImportTranslationsTrait;
use Shopwell\Core\Migration\Traits\Translations;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1773048327UpdateZugferdInvoiceTranslations extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1773048327;
    }

    public function update(Connection $connection): void
    {
        $types = [
            ZugferdRenderer::TYPE => [
                'zh' => ['name' => 'ZUGFeRD 发票'],
                'en' => ['name' => 'ZUGFeRD Invoice'],
            ],
            ZugferdEmbeddedRenderer::TYPE => [
                'zh' => ['name' => 'ZUGFeRD 发票（内嵌）'],
                'en' => ['name' => 'ZUGFeRD Invoice (embedded)'],
            ],
        ];

        foreach ($types as $technicalName => $translations) {
            $this->updateTranslation($technicalName, $translations, $connection);
        }
    }

    /**
     * @param array<string, array<string, string>> $translations
     */
    private function updateTranslation(string $technicalName, array $translations, Connection $connection): void
    {
        $typeId = $connection->fetchOne(
            'SELECT `id` FROM `document_type` WHERE technical_name = :technicalName',
            ['technicalName' => $technicalName]
        );

        if ($typeId === false) {
            return;
        }

        $translation = new Translations(
            array_merge(['document_type_id' => $typeId], $translations['zh']),
            array_merge(['document_type_id' => $typeId], $translations['en'])
        );

        $this->importTranslation(
            'document_type_translation',
            $translation,
            $connection
        );
    }
}

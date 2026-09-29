<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Checkout\Document\Renderer\ZugferdEmbeddedRenderer;
use Shopwell\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\Traits\ImportTranslationsTrait;
use Shopwell\Core\Migration\Traits\Translations;

/**
 * @internal
 */
#[Package('framework')]
class Migration1730790665ElectronicInvoice extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1730790665;
    }

    public function update(Connection $connection): void
    {
        $types = [
            ZugferdRenderer::TYPE => [
                'zh' => ['name' => '发票：ZUGFeRD 电子发票'],
                'en' => ['name' => 'Invoice: ZUGFeRD E-invoice'],
            ],
            ZugferdEmbeddedRenderer::TYPE => [
                'zh' => ['name' => '发票：内嵌 ZUGFeRD 电子发票的 PDF'],
                'en' => ['name' => 'Invoice: PDF with embedded ZUGFeRD E-invoice'],
            ],
        ];

        foreach ($types as $technicalName => $translations) {
            $this->addDocumentType($technicalName, $translations, $connection);
        }
    }

    /**
     * @param array<string, array<string, string>> $translations
     */
    private function addDocumentType(string $technicalName, array $translations, Connection $connection): void
    {
        $typeId = $connection->fetchOne(
            'SELECT `id` FROM `document_type` WHERE technical_name = :technicalName',
            ['technicalName' => $technicalName]
        );

        if ($typeId) {
            return;
        }

        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $typeId = Uuid::randomBytes();
        $connection->insert('document_type', ['id' => $typeId, 'technical_name' => $technicalName, 'created_at' => $createdAt]);

        $translation = new Translations(
            array_merge(['document_type_id' => $typeId], $translations['zh']),
            array_merge(['document_type_id' => $typeId], $translations['en'])
        );

        $this->importTranslation('document_type_translation', $translation, $connection);
    }
}

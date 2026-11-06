<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport;

use Shopwell\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - Will be removed
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@after-sales')]
class ImportExportProfileTranslationEntity extends TranslationEntity
{
    protected string $importExportProfileId;

    protected ?string $label = null;

    protected ImportExportProfileEntity $importExportProfile;

    public function __construct()
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));
    }

    public function getImportExportProfileId(): string
    {
        return $this->importExportProfileId;
    }

    public function setImportExportProfileId(string $importExportProfileId): void
    {
        $this->importExportProfileId = $importExportProfileId;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getImportExportProfile(): ImportExportProfileEntity
    {
        return $this->importExportProfile;
    }

    public function setImportExportProfile(ImportExportProfileEntity $importExportProfile): void
    {
        $this->importExportProfile = $importExportProfile;
    }
}

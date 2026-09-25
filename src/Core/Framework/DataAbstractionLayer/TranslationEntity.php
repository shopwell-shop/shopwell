<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Language\LanguageEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class TranslationEntity extends Entity
{
    protected string $languageId;

    protected ?LanguageEntity $language = null;

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(LanguageEntity $language): void
    {
        $this->language = $language;
    }
}

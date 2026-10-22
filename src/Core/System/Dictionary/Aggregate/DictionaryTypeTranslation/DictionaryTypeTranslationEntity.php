<?php declare(strict_types=1);

namespace Shopwell\Core\System\Dictionary\Aggregate\DictionaryTypeTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Dictionary\DictionaryTypeEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class DictionaryTypeTranslationEntity extends TranslationEntity
{
    protected string $dictionaryTypeId;

    protected ?string $label = null;

    protected ?DictionaryTypeEntity $dictionaryType = null;

    public function getDictionaryTypeId(): string
    {
        return $this->dictionaryTypeId;
    }

    public function setDictionaryTypeId(string $dictionaryTypeId): void
    {
        $this->dictionaryTypeId = $dictionaryTypeId;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getDictionaryType(): ?DictionaryTypeEntity
    {
        return $this->dictionaryType;
    }

    public function setDictionaryType(?DictionaryTypeEntity $dictionaryType): void
    {
        $this->dictionaryType = $dictionaryType;
    }
}

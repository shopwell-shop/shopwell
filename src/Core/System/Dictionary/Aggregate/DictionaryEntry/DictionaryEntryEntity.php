<?php declare(strict_types=1);

namespace Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry;

use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\Aggregate\DictionaryEntryTranslation\DictionaryEntryTranslationCollection;
use Shopwell\Core\System\Dictionary\DictionaryTypeEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class DictionaryEntryEntity extends Entity
{
    use EntityIdTrait;

    protected string $dictionaryTypeId;

    protected string $technicalName;

    protected int $position;

    protected bool $active;

    protected ?string $label = null;

    protected ?DictionaryEntryTranslationCollection $translations = null;

    protected ?DictionaryTypeEntity $dictionaryType = null;

    public function getDictionaryTypeId(): string
    {
        return $this->dictionaryTypeId;
    }

    public function setDictionaryTypeId(string $dictionaryTypeId): void
    {
        $this->dictionaryTypeId = $dictionaryTypeId;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getTranslations(): ?DictionaryEntryTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(DictionaryEntryTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getDictionaryType(): ?DictionaryTypeEntity
    {
        return $this->dictionaryType;
    }

    public function setDictionaryType(DictionaryTypeEntity $dictionaryType): void
    {
        $this->dictionaryType = $dictionaryType;
    }
}

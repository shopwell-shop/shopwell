<?php declare(strict_types=1);

namespace Shopwell\Core\System\Dictionary;

use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryCollection;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryTypeTranslation\DictionaryTypeTranslationCollection;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class DictionaryTypeEntity extends Entity
{
    use EntityIdTrait;

    protected string $technicalName;

    protected ?string $label = null;

    protected ?DictionaryTypeTranslationCollection $translations = null;

    protected ?DictionaryEntryCollection $entries = null;

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getTranslations(): ?DictionaryTypeTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(DictionaryTypeTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getEntries(): ?DictionaryEntryCollection
    {
        return $this->entries;
    }

    public function setEntries(DictionaryEntryCollection $entries): void
    {
        $this->entries = $entries;
    }
}

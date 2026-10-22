<?php declare(strict_types=1);

namespace Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\Aggregate\DictionaryEntryTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class DictionaryEntryTranslationEntity extends TranslationEntity
{
    protected string $dictionaryEntryId;

    protected ?string $label = null;

    protected ?DictionaryEntryEntity $dictionaryEntry = null;

    public function getDictionaryEntryId(): string
    {
        return $this->dictionaryEntryId;
    }

    public function setDictionaryEntryId(string $dictionaryEntryId): void
    {
        $this->dictionaryEntryId = $dictionaryEntryId;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getDictionaryEntry(): ?DictionaryEntryEntity
    {
        return $this->dictionaryEntry;
    }

    public function setDictionaryEntry(?DictionaryEntryEntity $dictionaryEntry): void
    {
        $this->dictionaryEntry = $dictionaryEntry;
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\System\Locale\Aggregate\LocaleTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Locale\LocaleEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class LocaleTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $localeId;

    protected ?string $name = null;

    protected ?string $territory = null;

    protected ?LocaleEntity $locale = null;

    public function getLocaleId(): string
    {
        return $this->localeId;
    }

    public function setLocaleId(string $localeId): void
    {
        $this->localeId = $localeId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getTerritory(): ?string
    {
        return $this->territory;
    }

    public function setTerritory(string $territory): void
    {
        $this->territory = $territory;
    }

    public function getLocale(): ?LocaleEntity
    {
        return $this->locale;
    }

    public function setLocale(LocaleEntity $locale): void
    {
        $this->locale = $locale;
    }
}

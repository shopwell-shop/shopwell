<?php declare(strict_types=1);

namespace Shopwell\Core\System\Language;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Locale\LocaleCollection;

/**
 * @extends EntityCollection<LanguageEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class LanguageCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getParentIds(): array
    {
        return $this->fmap(static fn (LanguageEntity $language) => $language->getParentId());
    }

    public function filterByParentId(string $id): LanguageCollection
    {
        return $this->filter(static fn (LanguageEntity $language) => $language->getParentId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getLocaleIds(): array
    {
        return $this->fmap(static fn (LanguageEntity $language) => $language->getLocaleId());
    }

    public function filterByLocaleId(string $id): LanguageCollection
    {
        return $this->filter(static fn (LanguageEntity $language) => $language->getLocaleId() === $id);
    }

    public function getLocales(): LocaleCollection
    {
        return new LocaleCollection(
            $this->fmap(static fn (LanguageEntity $language) => $language->getLocale())
        );
    }

    public function getApiAlias(): string
    {
        return 'language_collection';
    }

    protected function getExpectedClass(): string
    {
        return LanguageEntity::class;
    }
}

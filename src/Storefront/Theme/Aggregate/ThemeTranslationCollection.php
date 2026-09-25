<?php declare(strict_types=1);

namespace Shopwell\Storefront\Theme\Aggregate;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ThemeTranslationEntity>
 */
#[Package('discovery')]
class ThemeTranslationCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getThemeIds(): array
    {
        return $this->fmap(static fn (ThemeTranslationEntity $themeTranslation) => $themeTranslation->getThemeId());
    }

    public function filterByThemeId(string $id): self
    {
        return $this->filter(static fn (ThemeTranslationEntity $themeTranslation) => $themeTranslation->getThemeId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (ThemeTranslationEntity $themeTranslation) => $themeTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (ThemeTranslationEntity $themeTranslation) => $themeTranslation->getLanguageId() === $id);
    }

    protected function getExpectedClass(): string
    {
        return ThemeTranslationEntity::class;
    }
}

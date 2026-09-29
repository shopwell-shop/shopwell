<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\Traits;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class TranslationWriteResult
{
    /**
     * @param string[] $englishLanguages
     * @param string[] $zhCnLanguages
     */
    public function __construct(
        private readonly array $englishLanguages,
        private readonly array $zhCnLanguages
    ) {
    }

    /**
     * @return array<string>
     */
    public function getEnglishLanguages(): array
    {
        return $this->englishLanguages;
    }

    /**
     * @return array<string>
     */
    public function getZhCnLanguages(): array
    {
        return $this->zhCnLanguages;
    }

    public function hasWrittenEnglishTranslations(): bool
    {
        return $this->englishLanguages !== [];
    }

    public function hasWrittenZhCnTranslations(): bool
    {
        return $this->getZhCnLanguages() !== [];
    }
}

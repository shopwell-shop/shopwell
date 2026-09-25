<?php declare(strict_types=1);

namespace Shopwell\Core\System\Snippet\DataTransfer\Language;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Snippet\SnippetException;
use Shopwell\Core\System\Snippet\SnippetPatterns;
use Symfony\Component\Intl\Locales;

/**
 * @internal
 */
#[Package('discovery')]
class Language
{
    public function __construct(
        public readonly string $locale,
        public readonly string $name,
    ) {
        $this->validateLocale($locale);
    }

    private function validateLocale(string $locale): void
    {
        if (\array_key_exists($locale, SnippetPatterns::ALLOWED_PSEUDO_LOCALES)) {
            return;
        }

        if (str_contains($locale, '-')) {
            // Symfony expects underscores instead of hyphens in locale identifiers
            $locale = str_replace('-', '_', $locale);
        }

        if (!Locales::exists($locale)) {
            throw SnippetException::localeDoesNotExist($locale);
        }
    }
}

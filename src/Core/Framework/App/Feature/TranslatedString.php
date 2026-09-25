<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Feature;

use Shopwell\Core\Framework\Log\Package;

/**
 * A value translated per locale (locale code => value).
 *
 * @codeCoverageIgnore
 *
 * @internal
 */
#[Package('framework')]
final readonly class TranslatedString
{
    /**
     * @param array<string, string> $translations locale code => value
     */
    public function __construct(private array $translations)
    {
    }

    public function forLocale(string $locale): ?string
    {
        return $this->translations[$locale] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->translations;
    }
}

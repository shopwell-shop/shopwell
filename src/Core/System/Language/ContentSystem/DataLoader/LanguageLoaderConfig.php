<?php declare(strict_types=1);

namespace Shopwell\Core\System\Language\ContentSystem\DataLoader;

use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopwell\Core\Framework\Log\Package;

/**
 * Configuration for language data loader.
 *
 * @phpstan-type LanguageLoaderConfigData array{
 *   associations?: list<non-empty-string>
 * }
 *
 * @internal
 */
#[Package('framework')]
final readonly class LanguageLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @param list<non-empty-string> $associations Additional associations to load
     */
    public function __construct(
        public array $associations = [],
    ) {
    }

    /**
     * @return LanguageLoaderConfigData
     */
    public function jsonSerialize(): array
    {
        $data = [];

        if ($this->associations !== []) {
            $data['associations'] = $this->associations;
        }

        return $data;
    }
}

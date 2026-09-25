<?php declare(strict_types=1);

namespace Shopwell\Core\System\Snippet\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 *
 * @experimental stableVersion:v6.8.0 feature:STORE_API_SNIPPETS
 */
#[Package('discovery')]
final class SnippetSetResult extends Struct
{
    /**
     * @param array<string, string> $snippets
     */
    public function __construct(
        public string $languageId,
        public string $locale,
        public ?string $fallbackLocale,
        public ?string $snippetSetId,
        public string $hash,
        public array $snippets,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'snippet_set_result';
    }
}

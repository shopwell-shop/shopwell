<?php

declare(strict_types=1);

namespace Shopwell\Core\System\Snippet\Extension;

use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Translation\MessageCatalogueInterface;

/**
 * @extends Extension<array<string, string>>
 */
#[Package('discovery')]
final class StorefrontSnippetsExtension extends Extension
{
    public const NAME = 'storefront.snippets';

    /**
     * @internal shopwell owns the __constructor, but the properties are public API
     *
     * @param array<string, string> $snippets
     * @param string[] $unusedThemes
     */
    public function __construct(
        public array $snippets,
        public readonly string $locale,
        public readonly MessageCatalogueInterface $catalog,
        public readonly string $snippetSetId,
        public readonly ?string $fallbackLocale,
        public readonly ?string $salesChannelId,
        public readonly array $unusedThemes
    ) {
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\System\Snippet\Files;

use Shopwell\Core\Framework\Log\Package;

/**
 * Storefront snippets stored in the private filesystem (`shopwell.filesystem.private`).
 *
 * Every `<DIRECTORY>/<source>/<name>.<language>.json` or `<name>.<locale>.json` (e.g. `snippets/storefront/MyIntegration/storefront.de.json`)
 * is loaded as the lowest-priority snippet layer, so snippet files shipped by the core, plugins or apps
 * always win. The `<source>` directory is optional: files placed directly below `<DIRECTORY>` are attributed
 * to the author `custom`, with their domain (`storefront` for `storefront.de.json`) as technical name.
 * Clear the cache after writing, the storefront caches its translation catalogues.
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
final class FilesystemStorefrontSnippets
{
    public const DIRECTORY = 'snippets/storefront';

    public const ROOT_AUTHOR = 'custom';

    public static function directoryFor(string $source): string
    {
        return self::DIRECTORY . '/' . $source;
    }
}

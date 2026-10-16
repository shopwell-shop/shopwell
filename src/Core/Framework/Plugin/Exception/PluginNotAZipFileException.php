<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Plugin\Exception;

use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use StoreException::pluginNotAZipFile instead
 */
#[Package('framework')]
class PluginNotAZipFileException extends ShopwellHttpException
{
    public function __construct(string $mimeType)
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct(
            'Given file must be a zip file. Given: {{ mimeType }}',
            ['mimeType' => $mimeType]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__PLUGIN_NOT_A_ZIP_FILE';
    }
}

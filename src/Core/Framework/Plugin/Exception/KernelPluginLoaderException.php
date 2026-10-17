<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Plugin\Exception;

use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;

/**
 * @deprecated tag:v6.8.0 - Will be removed. Use \Shopwell\Core\Framework\Plugin\PluginException::kernelPluginLoaderError instead
 */
#[Package('framework')]
class KernelPluginLoaderException extends ShopwellHttpException
{
    public function __construct(
        string $plugin,
        string $reason
    ) {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct(
            'Failed to load plugin "{{ plugin }}". Reason: {{ reason }}',
            ['plugin' => $plugin, 'reason' => $reason]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__KERNEL_PLUGIN_LOADER_ERROR';
    }
}

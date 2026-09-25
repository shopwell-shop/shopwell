<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Plugin\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;

/**
 * @deprecated tag:v6.8.0 - reason:remove-exception - Will be removed. Use \Shopwell\Core\Framework\Plugin\PluginException::kernelPluginLoaderError instead
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class KernelPluginLoaderException extends ShopwellHttpException
{
    public function __construct(
        string $plugin,
        string $reason
    ) {
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

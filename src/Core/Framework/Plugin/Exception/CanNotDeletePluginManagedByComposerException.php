<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Plugin\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class CanNotDeletePluginManagedByComposerException extends ShopwellHttpException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            'Can not delete plugin. Please contact your system administrator. Error: {{ reason }}',
            ['reason' => $reason]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__STORE_CANNOT_DELETE_PLUGIN_MANAGED_BY_SHOPWARE';
    }
}

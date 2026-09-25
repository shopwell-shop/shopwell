<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class LiveVersionDeleteException extends ShopwellHttpException
{
    public function __construct()
    {
        parent::__construct('Live version can not be deleted. Delete entity instead.');
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__LIVE_VERSION_DELETE';
    }
}
